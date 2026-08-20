QUICK CHAT — SELF-HOSTED, NO DEPENDENCIES
==========================================

WHAT'S INSIDE
  index.html   - frontend (HTML/CSS/JS, no external libraries)
  api.php      - backend for text messages (fetch, send, clear room)
  upload.php   - backend for file uploads (images, docs, audio, video, zip)
  common.php   - shared storage helpers used by api.php and upload.php
  data/        - JSON files holding each room's message history
                 (.htaccess blocks direct browser access to this folder)
  files/       - uploaded files, organized by room
                 (.htaccess blocks folder listing, but direct file links
                 still work so files can be viewed/downloaded)

REQUIREMENTS
  Any PHP host works (PHP 7+). No database needed. Confirmed compatible
  with InfinityFree and similar free shared hosting.

HOW TO DEPLOY
  1. Upload the whole "quickchat" folder (all files above) to your site
     via FTP or your host's File Manager.
     Example: htdocs/quickchat/  or  public_html/quickchat/

  2. Make sure "data" and "files" folders are writable by PHP.
     Most hosts default to 755 permissions, which is fine. If uploads
     or messages fail, try setting these two folders to 755 or 775
     via your host's file manager (right-click > File Permissions).

  3. Visit https://yourdomain.com/quickchat/ on BOTH your phone and PC.

  4. On each device, enter the SAME room name (e.g. "raj-room-1") and
     any username, then tap "Join Chat". Messages and files sync
     automatically every 2 seconds.

AUTOMATIC CLEANUP (24-HOUR EXPIRY)
  Messages and their uploaded files are automatically deleted once
  they're older than 24 hours — no manual "Clear" tap needed, and no
  cron job required either (most free hosts don't support those
  reliably anyway).

  How it works: every time the chat is loaded or polled, the server
  quietly checks each room for messages older than 24 hours and
  removes them (deleting any attached files too). This only runs when
  someone actually opens/uses a room, so a completely inactive room
  won't be cleaned until someone visits it again — that's fine, since
  there's nothing to clean up until then anyway.

  To change the expiry window, edit $messageExpirySeconds in
  common.php (currently set to 24 * 60 * 60 for 24 hours).

FILE SHARING
  Tap the paperclip icon to attach a file. Supported types:
    Images:    jpg, jpeg, png, gif, webp, svg, bmp   (shown inline)
    Audio:     mp3, wav, ogg, m4a, aac                (inline player)
    Video:     mp4, webm, mov, mkv, avi                (inline player)
    Documents: pdf, doc, docx, xls, xlsx, ppt, pptx,
               txt, csv, rtf                          (download card)
    Archives:  zip, rar, 7z                           (download card)

  Default max file size is 25 MB (set in upload.php as $maxFileSizeBytes).
  NOTE: your host's own PHP settings (upload_max_filesize and
  post_max_size in php.ini) may cap this lower — many free hosts
  default to around 10-20 MB per upload. If large files fail, check
  your host's control panel for a PHP settings section, or lower
  $maxFileSizeBytes in upload.php to match your host's limit.

  Uploaded files are stored permanently on the server under
  files/<room>/ until you tap "Clear", which deletes both the message
  history AND the uploaded files for that room.

AI CHAT (NO API KEY REQUIRED)
  Type a message starting with "@ai" or "/ai" followed by your question,
  e.g.:
      @ai what's a good name for a gaming website?
      /ai summarize what we just talked about

  The AI's reply is posted into the same room as a message from
  "AI Assistant" (visible to everyone in the room, on both devices).

  IMPORTANT: this call happens directly from your phone/PC's BROWSER
  to LLM7.io (https://llm7.io), not through your PHP server. This is
  intentional — many free hosts (including InfinityFree) block
  outgoing server-side requests to external APIs, which would make a
  server-side AI call fail silently or with a vague error. Calling
  from the browser instead means your device reaches the AI provider
  directly, bypassing that restriction. Once a reply comes back, it's
  saved through api.php like any normal message, so it still syncs to
  both devices.

  Good to know:
    - LLM7.io's anonymous tier is rate-limited (documented around 30
      requests/minute per IP). If you get an error, wait a bit and
      retry.
    - Free/keyless AI providers change their policies fairly often.
      If @ai stops working, open index.html and look at the askAi()
      function in the <script> section — that's the only place to
      update if you want to point it at a different free provider.
    - The AI gets a little conversation context (the last ~8 text
      messages you've seen locally) so it can follow along, not full
      memory of the whole chat history.

CUSTOMIZING
  - Change POLL_INTERVAL_MS in index.html to poll faster/slower.
  - Change $maxMessages in common.php to keep more/less chat history.
  - Change $allowedExtensions in upload.php to allow/restrict file types.
  - To host the PHP backend on a different domain than index.html,
    update API_URL and UPLOAD_URL at the top of the <script> block.

NOTES
  - This has no login system — the "username" is just a display
    label, not an authenticated account. Anyone who knows your room
    name and URL can join that room and see/download its files.
    Pick a non-obvious room name for basic privacy.
  - Messages are stored as plain JSON files in data/, not a database.
    Fine for personal use; not built for high traffic or many
    simultaneous large uploads.
