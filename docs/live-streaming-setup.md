# Live Streaming Setup — Dedicated VPS

This is separate from your Hostinger Cloud Hosting (which runs the PHP app).
You'll spin up a small, cheap VPS whose only job is: receive video from the
captain's phone (RTMP) and serve it back out to visitors' browsers (HLS).

## 1. Get a VPS

Any of these work fine — pick one:
- **DigitalOcean** — $6/mo droplet, 1GB RAM, Ubuntu 24.04
- **Vultr** — similar pricing, same setup steps
- **Contabo** — cheaper, slightly slower spin-up, fine for one stream

When creating it: choose **Ubuntu 24.04 LTS**, the cheapest tier (1 vCPU /
1GB RAM is enough for one stream), and note the **root password** and
**IP address** you're given.

## 2. Point a subdomain at it

In your DNS (wherever `capitony.live`'s DNS is managed — likely Hostinger's
DNS zone editor):
- Add an **A record**: `stream.capitony.live` → your new VPS's IP address
- Wait a few minutes for it to propagate (check with `ping stream.capitony.live`)

## 3. SSH in and install Docker

```bash
ssh root@your-vps-ip
apt update && apt upgrade -y
curl -fsSL https://get.docker.com | sh
```

Docker keeps this simple — no compiling nginx from source, no dependency
hunting. One container does the whole job.

## 4. Run the RTMP/HLS server

```bash
mkdir -p /opt/rtmp/hls
docker run -d \
  --name capitony-stream \
  --restart unless-stopped \
  -p 1935:1935 \
  -p 8080:80 \
  -v /opt/rtmp/hls:/opt/data/hls \
  alfg/nginx-rtmp
```

This exposes:
- **Port 1935** — where the captain's phone pushes video *to* (RTMP)
- **Port 8080** — where browsers pull video *from* (HLS)

## 5. Open the firewall

```bash
ufw allow 22/tcp
ufw allow 1935/tcp
ufw allow 8080/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

## 6. Put HTTPS in front of the HLS output

Browsers block loading plain `http://` video from an `https://` page
(capitony.live has SSL). Put Nginx + Let's Encrypt in front of port 8080:

```bash
apt install -y nginx certbot python3-certbot-nginx
```

Create `/etc/nginx/sites-available/stream`:
```nginx
server {
    listen 80;
    server_name stream.capitony.live;
    location / {
        proxy_pass http://127.0.0.1:8080;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/stream /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
certbot --nginx -d stream.capitony.live
```

Certbot will auto-configure HTTPS and redirect. After this, your HLS
playback URL is:
```
https://stream.capitony.live/live/{stream_key}.m3u8
```

**Important — don't add a CORS header here.** The `alfg/nginx-rtmp`
container already sends `Access-Control-Allow-Origin: *` on its own for
every HLS response. If the reverse-proxy config above also adds
`add_header Access-Control-Allow-Origin *;`, browsers will receive the
header **twice** (`*, *`) and reject it outright — the video will load
fine over plain HTTP/curl but silently fail in every browser with a CORS
error, which is confusing to debug because the network request itself
returns 200 OK. If you ever recreate this config from scratch, leave
that line out entirely.

## 7. Streaming from the captain's phone

**Note on this image's naming**: the `alfg/nginx-rtmp` image uses different
path segments for pushing video in vs. pulling it back out — push goes to
`/stream/`, playback comes from `/live/`. Easy to mix up; both are shown
correctly below.

**The stream key belongs to the boat, not the session** — it's set once
in **Admin → Boats** (edit the boat, the key and full RTMP URL are shown
there, with a "Regenerate" option only for if it's ever compromised).
Clicking "Go Live" in the captain dashboard never changes this key — it's
a pure database toggle. This means Larix only ever needs to be configured
**once, ever**, not before every trip — important since Larix typically
runs on separate hardware (a 360 camera's own phone/app) from whatever
the captain uses to click "Go Live."

Install **Larix Broadcaster** (free, iOS + Android). In its settings:
- Tap the gear icon → **Connections** → **+** to add a new connection
- **Name**: anything, e.g. `Capitony`
- **URL**: the RTMP address and the boat's permanent stream key combined
  into one field, joined by `/` — copy the exact URL shown on that boat's
  edit page in Admin → Boats, e.g.:
  ```
  rtmp://stream.capitony.live/stream/a1b2c3d4e5f6...
  ```
- Save, make sure the connection's checkbox is ticked

From here on, every trip is just: click "Go Live" in the captain
dashboard, then tap the red record button in Larix (same saved
connection, no editing needed) — and "End Live Session" + stopping Larix
when done. No copying keys between devices, ever again after this
one-time setup.

## 8. Tell the app where to find the stream

In `config/config.php` on the Hostinger side, set:
```php
define('STREAM_HLS_BASE_URL', 'https://stream.capitony.live/live/');
```

The homepage's live player (already built — see below) reads this and
constructs the playback URL automatically from the active session's
stream key.

## Ongoing costs & maintenance

- VPS: ~$6/month, billed by whichever provider you pick
- The Docker container restarts itself automatically on reboot
  (`--restart unless-stopped`)
- Renew HTTPS: certbot sets up auto-renewal by default — nothing to do
- If the stream ever looks broken: `docker logs capitony-stream` on the
  VPS is the first place to check
- **Old video segments are cleaned up automatically.** nginx-rtmp writes
  a `.ts` file to `/opt/rtmp/hls/` every few seconds while streaming and
  never deletes them on its own — left unchecked, disk usage grows every
  time a trip goes live. A cron job (`/opt/rtmp/cleanup.sh`, scheduled
  daily at 3 AM) deletes anything older than 7 days and tidies up empty
  stream folders. Check `/opt/rtmp/cleanup.log` to confirm it's actually
  running, and `df -h` periodically to keep an eye on disk space.
