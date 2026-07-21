<?php
/**
 * Expects $liveSession to be an array with at least 'stream_key' and
 * optionally 'boat_name'. Include only when a live session actually exists.
 */
if (!defined('STREAM_HLS_BASE_URL')) {
    // Server hasn't been updated with the streaming config yet — fail
    // quietly rather than fatally erroring the whole homepage.
    ?>
    <div class="live-banner">Live session is starting — video setup in progress, check back shortly.</div>
    <?php
    return;
}
$hlsUrl = STREAM_HLS_BASE_URL . $liveSession['stream_key'] . '.m3u8';
?>
<div class="live-player-wrap" id="live">
  <div class="live-player">
    <video id="capitonyLiveVideo" muted playsinline poster="/assets/img/deck-catch-day.jpg"></video>
    <div class="live-player-badge"><span class="live-dot"></span> LIVE</div>
    <div class="live-player-status" id="liveStatusMsg">Connecting to the boat…</div>
  </div>
  <p class="live-player-caption">Streaming from <?= e($liveSession['boat_name'] ?? 'the boat') ?> · Dubai Marina</p>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.5.15/hls.min.js"></script>
<script>
(function () {
  var video = document.getElementById('capitonyLiveVideo');
  var status = document.getElementById('liveStatusMsg');
  var src = <?= json_encode($hlsUrl) ?>;
  var retryDelay = 5000;

  function showStatus(text) { status.textContent = text; status.style.display = 'block'; }
  function hideStatus() { status.style.display = 'none'; }

  function attempt() {
    if (window.Hls && Hls.isSupported()) {
      var hls = new Hls({ liveDurationInfinity: true });
      hls.loadSource(src);
      hls.attachMedia(video);
      hls.on(Hls.Events.MANIFEST_PARSED, function () {
        hideStatus();
        video.play().catch(function () { showStatus('Tap to watch'); });
      });
      hls.on(Hls.Events.ERROR, function (event, data) {
        if (data.fatal) {
          showStatus('Stream starting soon — the boat may still be connecting.');
          setTimeout(attempt, retryDelay);
        }
      });
    } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
      // Safari has native HLS support, no library needed.
      video.src = src;
      video.addEventListener('loadedmetadata', hideStatus);
      video.addEventListener('error', function () {
        showStatus('Stream starting soon — the boat may still be connecting.');
        setTimeout(attempt, retryDelay);
      });
    } else {
      showStatus('Your browser can\'t play this stream — try Chrome, Safari, or Edge.');
    }
  }

  showStatus('Connecting to the boat…');
  attempt();

  status.addEventListener('click', function () { video.play(); });
})();
</script>
