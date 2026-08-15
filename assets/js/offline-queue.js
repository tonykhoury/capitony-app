/**
 * Offline queue for captain forms that include a file upload (catch
 * photos, expense receipts) — localStorage can't hold File objects, so
 * this uses IndexedDB instead, which can.
 *
 * Honest scope: this queues a submission when the browser is ALREADY
 * known-offline (navigator.onLine === false) at the moment "submit" is
 * pressed — the common real case for a boat genuinely out of signal
 * range. If the connection drops mid-request instead (online at submit
 * time, then fails partway through), the browser shows its own native
 * error rather than a graceful queue — a deliberate simplicity
 * trade-off, since handling that case properly would mean converting
 * these pages into JSON APIs rather than the traditional server-rendered
 * forms they are today.
 */
(function () {
  var DB_NAME = 'capitony-offline-queue';
  var STORE_NAME = 'pending';

  function openDb() {
    return new Promise(function (resolve, reject) {
      var req = indexedDB.open(DB_NAME, 1);
      req.onupgradeneeded = function () {
        req.result.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
      };
      req.onsuccess = function () { resolve(req.result); };
      req.onerror = function () { reject(req.error); };
    });
  }

  function queueSubmission(url, form, description) {
    var entries = [];
    new FormData(form).forEach(function (value, key) { entries.push([key, value]); });

    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).add({
          url: url, entries: entries, description: description, queuedAt: Date.now(),
        });
        tx.oncomplete = resolve;
        tx.onerror = function () { reject(tx.error); };
      });
    });
  }

  function getAllPending() {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var req = db.transaction(STORE_NAME, 'readonly').objectStore(STORE_NAME).getAll();
        req.onsuccess = function () { resolve(req.result); };
        req.onerror = function () { reject(req.error); };
      });
    });
  }

  function deletePending(id) {
    return openDb().then(function (db) {
      return new Promise(function (resolve, reject) {
        var tx = db.transaction(STORE_NAME, 'readwrite');
        tx.objectStore(STORE_NAME).delete(id);
        tx.oncomplete = resolve;
        tx.onerror = function () { reject(tx.error); };
      });
    });
  }

  function flushQueue() {
    return getAllPending().then(function (items) {
      updateBadge(items.length);
      var chain = Promise.resolve();
      items.forEach(function (item) {
        chain = chain.then(function () {
          var fd = new FormData();
          item.entries.forEach(function (pair) { fd.append(pair[0], pair[1]); });
          return fetch(item.url, { method: 'POST', body: fd })
            .then(function (res) {
              if (res.ok) { return deletePending(item.id); }
            })
            .catch(function () { /* still offline — leave queued, try again next time */ });
        });
      });
      return chain.then(function () { return getAllPending(); }).then(function (remaining) {
        updateBadge(remaining.length);
      });
    });
  }

  function updateBadge(count) {
    var badge = document.getElementById('offlineQueueBadge');
    if (!badge) return;
    if (count > 0) {
      badge.textContent = count + ' queued — will send once online';
      badge.style.display = 'block';
    } else {
      badge.style.display = 'none';
    }
  }

  /** Wires up a form: submits normally if online, queues if genuinely offline. */
  window.enableOfflineQueue = function (formId, description) {
    var form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', function (e) {
      if (!navigator.onLine) {
        e.preventDefault();
        queueSubmission(form.action || window.location.href, form, description).then(function () {
          alert('No connection right now — saved and will send automatically once you\'re back online.');
          getAllPending().then(function (items) { updateBadge(items.length); });
        });
      }
      // else: let it submit normally — see the file-level comment on scope.
    });
  };

  window.addEventListener('online', flushQueue);
  document.addEventListener('DOMContentLoaded', flushQueue);
  setInterval(flushQueue, 30000); // catches flaky connections that don't reliably fire the 'online' event
})();
