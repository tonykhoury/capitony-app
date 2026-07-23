<?php
/**
 * Expects $chatLiveSessionId (int) and $chatIsCaptain (bool) to be set
 * before including. Captain mode skips the name prompt (identity comes
 * from the logged-in session) and styles their own messages distinctly.
 */
$storedName = $chatIsCaptain ? '' : (get_visitor_chat_name() ?? '');
?>
<div class="chat-widget" data-session-id="<?= (int)$chatLiveSessionId ?>" data-is-captain="<?= $chatIsCaptain ? '1' : '0' ?>">
  <div class="chat-messages" id="chatMessages"></div>

  <?php if (!$chatIsCaptain): ?>
  <div class="chat-name-row">
    <input type="text" id="chatSenderName" placeholder="Your name" value="<?= e($storedName) ?>" maxlength="60">
  </div>
  <?php endif; ?>

  <div class="chat-input-row">
    <input type="text" id="chatBodyText" placeholder="Say something to the boat..." maxlength="1000">
    <button type="button" id="chatMicBtn" class="chat-mic-btn" title="Record a voice note">🎤</button>
    <button type="button" id="chatSendBtn" class="chat-send-btn">Send</button>
  </div>
  <div id="chatRecordingStatus" class="chat-recording-status" style="display:none;">
    ● Recording... <button type="button" id="chatStopRecordBtn">Stop & Send</button>
    <button type="button" id="chatCancelRecordBtn">Cancel</button>
  </div>
  <div id="chatError" class="chat-error" style="display:none;"></div>
</div>

<style>
.chat-widget{background:var(--slate,#1E2B2E); border:1px solid var(--slate-line,rgba(255,255,255,0.1)); display:flex; flex-direction:column; height:420px;}
.chat-messages{flex:1; overflow-y:auto; padding:14px; display:flex; flex-direction:column; gap:10px;}
.chat-msg{font-size:0.85rem; color:#F6F9F7;}
.chat-msg .who{font-family:var(--mono,monospace); font-size:0.72rem; color:var(--sun,#E08A3C); margin-bottom:2px;}
.chat-msg.captain .who{color:#7FD1E0;}
.chat-msg audio{max-width:220px; height:32px;}
.chat-name-row, .chat-input-row{display:flex; gap:8px; padding:10px 14px; border-top:1px solid var(--slate-line,rgba(255,255,255,0.1));}
.chat-name-row input, .chat-input-row input[type=text]{flex:1; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); color:#fff; padding:9px 12px; font-family:var(--body,serif); font-size:0.88rem; border-radius:2px;}
.chat-mic-btn, .chat-send-btn{background:var(--sun,#E08A3C); color:#fff; border:none; padding:0 14px; cursor:pointer; font-size:0.85rem; border-radius:2px;}
.chat-mic-btn.recording{background:var(--danger,#B3401F); animation:pulse 1.2s infinite;}
.chat-recording-status{padding:8px 14px; color:var(--danger,#e05a3a); font-size:0.82rem; display:flex; gap:10px; align-items:center;}
.chat-recording-status button{background:rgba(255,255,255,0.1); color:#fff; border:none; padding:4px 10px; font-size:0.75rem; cursor:pointer; border-radius:2px;}
.chat-error{padding:6px 14px; color:#ff8a65; font-size:0.78rem;}
</style>

<script>
(function() {
  var sessionId = <?= (int)$chatLiveSessionId ?>;
  var isCaptain = <?= $chatIsCaptain ? 'true' : 'false' ?>;
  var lastId = 0;
  var messagesEl = document.getElementById('chatMessages');
  var errorEl = document.getElementById('chatError');

  function showError(msg) {
    errorEl.textContent = msg;
    errorEl.style.display = 'block';
    setTimeout(function() { errorEl.style.display = 'none'; }, 4000);
  }

  function renderMessage(m) {
    var div = document.createElement('div');
    div.className = 'chat-msg' + (m.is_captain ? ' captain' : '');
    var who = document.createElement('div');
    who.className = 'who';
    who.textContent = (m.is_captain ? '⚓ ' : '') + m.sender_name + ' · ' + m.time;
    div.appendChild(who);
    if (m.message_type === 'voice' && m.audio_path) {
      var audio = document.createElement('audio');
      audio.controls = true;
      audio.src = m.audio_path;
      div.appendChild(audio);
    } else {
      var text = document.createElement('div');
      text.textContent = m.body_text || '';
      div.appendChild(text);
    }
    messagesEl.appendChild(div);
  }

  function poll() {
    fetch('/chat-poll.php?live_session_id=' + sessionId + '&since_id=' + lastId)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.messages && data.messages.length) {
          var wasAtBottom = messagesEl.scrollHeight - messagesEl.scrollTop <= messagesEl.clientHeight + 40;
          data.messages.forEach(function(m) {
            renderMessage(m);
            lastId = Math.max(lastId, m.id);
          });
          if (wasAtBottom) messagesEl.scrollTop = messagesEl.scrollHeight;
        }
      })
      .catch(function() { /* silent — next poll will retry */ });
  }

  poll();
  setInterval(poll, 4000);

  function getSenderName() {
    if (isCaptain) return null; // server fills this in from the session
    var el = document.getElementById('chatSenderName');
    return el ? el.value.trim() : '';
  }

  document.getElementById('chatSendBtn').addEventListener('click', function() {
    var body = document.getElementById('chatBodyText');
    var text = body.value.trim();
    if (!text) return;
    if (!isCaptain && !getSenderName()) { showError('Enter your name first.'); return; }

    var fd = new FormData();
    fd.append('live_session_id', sessionId);
    fd.append('message_type', 'text');
    fd.append('body_text', text);
    if (isCaptain) { fd.append('as_captain', '1'); } else { fd.append('sender_name', getSenderName()); }

    fetch('/chat-send.php', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.error) { showError(data.error); return; }
        body.value = '';
        poll();
      })
      .catch(function() { showError('Could not send — check your connection.'); });
  });

  document.getElementById('chatBodyText').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') document.getElementById('chatSendBtn').click();
  });

  // --- Voice notes via MediaRecorder ---
  var mediaRecorder = null;
  var audioChunks = [];

  document.getElementById('chatMicBtn').addEventListener('click', function() {
    if (!isCaptain && !getSenderName()) { showError('Enter your name first.'); return; }
    if (!navigator.mediaDevices || !window.MediaRecorder) {
      showError('Voice notes aren\'t supported in this browser.');
      return;
    }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function(stream) {
      audioChunks = [];
      mediaRecorder = new MediaRecorder(stream);
      mediaRecorder.ondataavailable = function(e) { audioChunks.push(e.data); };
      mediaRecorder.start();
      document.getElementById('chatMicBtn').classList.add('recording');
      document.getElementById('chatRecordingStatus').style.display = 'flex';
    }).catch(function() { showError('Microphone access denied.'); });
  });

  function stopRecording(send) {
    if (!mediaRecorder) return;
    mediaRecorder.onstop = function() {
      document.getElementById('chatMicBtn').classList.remove('recording');
      document.getElementById('chatRecordingStatus').style.display = 'none';
      mediaRecorder.stream.getTracks().forEach(function(t) { t.stop(); });

      if (!send) { audioChunks = []; return; }

      var blob = new Blob(audioChunks, { type: 'audio/webm' });
      var fd = new FormData();
      fd.append('live_session_id', sessionId);
      fd.append('message_type', 'voice');
      fd.append('audio', blob, 'voice.webm');
      if (isCaptain) { fd.append('as_captain', '1'); } else { fd.append('sender_name', getSenderName()); }

      fetch('/chat-send.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (data.error) { showError(data.error); return; }
          poll();
        })
        .catch(function() { showError('Could not send voice note.'); });
    };
    mediaRecorder.stop();
  }

  document.getElementById('chatStopRecordBtn').addEventListener('click', function() { stopRecording(true); });
  document.getElementById('chatCancelRecordBtn').addEventListener('click', function() { stopRecording(false); });
})();
</script>
