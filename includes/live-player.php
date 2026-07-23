<?php
/**
 * Expects $liveSession with 'stream_key', 'session_status', 'live_session_id',
 * and optionally 'boat_name'. Include only when $liveTrip exists (a trip
 * that isn't completed and has had at least one live session).
 *
 * Video only shows while session_status is actually 'live' — chat stays
 * available the whole time the trip is active, even between streaming
 * bursts, since a captain replying should never be replying into a void
 * where the visitor's chat UI already vanished.
 */
$isActivelyStreaming = $liveSession['session_status'] === 'live';

if ($isActivelyStreaming && !defined('STREAM_HLS_BASE_URL')) {
    // Server hasn't been updated with the streaming config yet — fail
    // quietly rather than fatally erroring the whole homepage.
    ?>
    <div class="live-banner">Live session is starting — video setup in progress, check back shortly.</div>
    <?php
    $isActivelyStreaming = false;
}
?>
<div class="live-player-wrap" id="live">
  <?php if ($isActivelyStreaming): ?>
    <?php $hlsUrl = STREAM_HLS_BASE_URL . $liveSession['stream_key'] . '.m3u8'; ?>
    <div class="live-player">
      <video id="capitonyLiveVideo" muted playsinline></video>
      <div class="live-player-logo-overlay" id="liveLogoOverlay">
        <img src="/assets/img/logo.png" alt="Capitony">
      </div>
      <div class="live-player-badge"><span class="live-dot"></span> LIVE</div>
      <div class="live-player-status" id="liveStatusMsg">Connecting to the boat…</div>
    </div>
    <p class="live-player-caption">Streaming from <?= e($liveSession['boat_name'] ?? 'the boat') ?> · Dubai Marina</p>
  <?php else: ?>
    <div class="live-player" style="display:flex; align-items:center; justify-content:center; flex-direction:column; gap:10px;">
      <img src="/assets/img/logo.png" alt="Capitony" style="width:100px; opacity:0.8;">
      <p style="color:var(--sky-pale); font-family:var(--mono); font-size:0.85rem; text-align:center; padding:0 20px;">
        Not streaming right now — <?= e($liveSession['boat_name'] ?? 'the boat') ?> is still out on this trip. Chat with the captain below.
      </p>
    </div>
  <?php endif; ?>

  <?php $chatLiveSessionId = $liveSession['live_session_id']; $chatIsCaptain = false; require __DIR__ . '/chat-widget.php'; ?>
</div>

<?php if ($isActivelyStreaming): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/hls.js/1.5.15/hls.min.js"></script>
<script>
(function () {
  var video = document.getElementById('capitonyLiveVideo');
  var status = document.getElementById('liveStatusMsg');
  var logoOverlay = document.getElementById('liveLogoOverlay');
  var src = <?= json_encode($hlsUrl) ?>;
  var retryDelay = 5000;

  function showStatus(text) { status.textContent = text; status.style.display = 'block'; }
  function hideStatus() { status.style.display = 'none'; }

  // Real video frames are actually arriving — safe to remove the logo
  // placeholder now, not just when hls.js says the manifest parsed
  // (which can happen before any frame is actually decoded/visible).
  video.addEventListener('playing', function () { logoOverlay.style.display = 'none'; });

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
<?php endif; ?>
