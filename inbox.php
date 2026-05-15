<?php
// inbox.php — Private message inbox with Supabase + Gmail SMTP + Gmail IMAP reply sync support.

require_once 'db.php';

session_start();

// ── Logout ──────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: inbox.php');
    exit;
}

// ── Login handler ────────────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === INBOX_PASSWORD) {
        $_SESSION['lin_inbox'] = true;
        header('Location: inbox.php' . (isset($_GET['id']) ? '?id=' . (int)$_GET['id'] : ''));
        exit;
    } else {
        $error = 'Incorrect password. Please try again.';
    }
}

$loggedIn = !empty($_SESSION['lin_inbox']);

// ── Reply sender ─────────────────────────────────────────────
$replyStatus = null;

if ($loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_to_email'])) {
    $replyTo      = trim($_POST['reply_to_email']);
    $replyName    = trim($_POST['reply_to_name']);
    $replyBody    = trim($_POST['reply_body']);
    $replySubject = trim($_POST['reply_subject']) ?: 'Re: Your message';
    $msgId        = isset($_POST['msg_id']) ? (int)$_POST['msg_id'] : 0;

    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL) && $replyBody) {
        $sent = sendReply($replyTo, $replyName, $replySubject, $replyBody);
        $replyStatus = $sent ? 'sent' : 'error';

        if ($sent) {
            header('Location: inbox.php?id=' . $msgId . '&replied=1');
            exit;
        }
    } else {
        $replyStatus = 'error';
    }
}

// ── Actions only when logged in ──────────────────────────────
if ($loggedIn) {

    if (function_exists('syncGmailReplies')) {
        syncGmailReplies();
    }

    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        deleteMessage((int)$_GET['delete']);
        header('Location: inbox.php');
        exit;
    }

    if (isset($_GET['read']) && is_numeric($_GET['read'])) {
        markRead((int)$_GET['read']);
    }

    if (isset($_GET['unread']) && is_numeric($_GET['unread'])) {
        markUnread((int)$_GET['unread']);
    }

    if (isset($_GET['readall'])) {
        markAllRead();
        header('Location: inbox.php');
        exit;
    }

    if (isset($_GET['deleteread'])) {
        deleteReadMessages();
        header('Location: inbox.php');
        exit;
    }

    $filter      = $_GET['filter'] ?? 'all';
    $messages    = getMessages($filter);
    $totalCount  = countMessages();
    $unreadCount = countUnread();

    $openMsg = null;
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $openMsg = getMessage((int)$_GET['id']);

        if ($openMsg) {
            markRead((int)$_GET['id']);
            $openMsg['is_read'] = true;
        }
    }
}

function displaySourceLabel(array $msg): string {
    $source = $msg['source'] ?? '';
    if ($source === 'gmail_reply')   return 'Gmail Reply';
    if ($source === 'contact_form')  return 'Contact Form';
    return $source ?: 'Message';
}

function buildReplySubject(array $msg): string {
    $subject = trim($msg['subject'] ?? '');
    if ($subject === '') return 'Re: Your message';
    $subject = preg_replace('/^Re:\s*/i', '', $subject);
    return 'Re: ' . $subject;
}

function getInitials(string $name): string {
    $parts = array_filter(explode(' ', trim($name)));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>INBOX</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:          #080b12;
      --surface:     rgba(13,17,30,.92);
      --surface-2:   rgba(20,25,42,.95);
      --surface-3:   rgba(28,34,55,.98);
      --border:      rgba(255,255,255,.06);
      --border-md:   rgba(255,255,255,.11);
      --border-hi:   rgba(255,255,255,.2);
      --text:        #e8eaf0;
      --text-2:      #8b90a6;
      --text-3:      #4e546a;
      --accent:      #4f6ef7;
      --accent-dim:  rgba(79,110,247,.14);
      --accent-glow: rgba(79,110,247,.3);
      --green:       #34d399;
      --green-dim:   rgba(52,211,153,.12);
      --amber:       #fbbf24;
      --amber-dim:   rgba(251,191,36,.12);
      --red:         #f87171;
      --red-dim:     rgba(248,113,113,.12);
      --radius:      10px;
      --radius-lg:   16px;
    }
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    html, body { height:100%; scroll-behavior:smooth; }
    body {
      font-family: 'DM Sans', system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      height: 100%;
      font-size: 15px;
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      overflow: hidden;
    }
    a { text-decoration:none; color:inherit; }
    button { font-family:inherit; cursor:pointer; }

    /* ── ANIMATED MESH BACKGROUND ── */
    .bg-mesh {
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
    }
    .bg-mesh::before {
      content: '';
      position: absolute; inset: 0;
      background:
        radial-gradient(ellipse 70% 55% at 12% 8%,   rgba(79,110,247,.16) 0%, transparent 55%),
        radial-gradient(ellipse 55% 65% at 88% 85%,  rgba(110,59,220,.12) 0%, transparent 50%),
        radial-gradient(ellipse 45% 45% at 65% 42%,  rgba(52,211,153,.06) 0%, transparent 45%),
        radial-gradient(ellipse 80% 35% at 50% 105%, rgba(79,110,247,.08) 0%, transparent 55%);
      animation: meshPulse 14s ease-in-out infinite alternate;
    }
    .bg-mesh::after {
      content: '';
      position: absolute; inset: 0;
      background-image:
        linear-gradient(rgba(79,110,247,.028) 1px, transparent 1px),
        linear-gradient(90deg, rgba(79,110,247,.028) 1px, transparent 1px);
      background-size: 52px 52px;
    }
    @keyframes meshPulse {
      0%   { opacity: 1;   transform: scale(1);    }
      40%  { opacity: .65; transform: scale(1.06); }
      100% { opacity: .9;  transform: scale(1.02); }
    }

    /* ── SCROLLBARS ── */
    ::-webkit-scrollbar { width:4px; height:4px; }
    ::-webkit-scrollbar-track { background:transparent; }
    ::-webkit-scrollbar-thumb { background:rgba(79,110,247,.2); border-radius:999px; }
    ::-webkit-scrollbar-thumb:hover { background:rgba(79,110,247,.4); }

    /* ══════════════════════════════════════════
       LOGIN
    ══════════════════════════════════════════ */
    .login-page {
      position: relative; z-index: 1;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .login-card {
      width: 100%;
      max-width: 380px;
      background: var(--surface);
      border: 1px solid var(--border-md);
      border-radius: var(--radius-lg);
      padding: 48px 40px 44px;
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
    }
    .login-badge {
      width: 44px; height: 44px;
      border-radius: 12px;
      background: var(--accent-dim);
      border: 1px solid rgba(79,110,247,.3);
      display: flex; align-items:center; justify-content:center;
      margin-bottom: 28px;
    }
    .login-badge svg { width:20px; height:20px; stroke:var(--accent); fill:none; stroke-width:1.75; stroke-linecap:round; stroke-linejoin:round; }
    .login-card h1 {
      font-size: 19px;
      font-weight: 600;
      letter-spacing: -.3px;
      margin-bottom: 6px;
      color: var(--text);
    }
    .login-card p {
      font-size: 13px;
      color: var(--text-2);
      margin-bottom: 32px;
      font-weight: 300;
    }
    .field-label {
      display:block;
      font-size:11px;
      font-weight:500;
      letter-spacing:.6px;
      text-transform:uppercase;
      color:var(--text-3);
      margin-bottom:8px;
    }
    .login-input {
      width:100%;
      padding: 11px 14px;
      background: var(--surface-2);
      border: 1px solid var(--border-md);
      border-radius: var(--radius);
      color: var(--text);
      font-family: 'DM Mono', monospace;
      font-size: 14px;
      outline:none;
      transition: border-color .2s, box-shadow .2s;
      letter-spacing: 3px;
      margin-bottom: 16px;
    }
    .login-input::placeholder { letter-spacing:.5px; color:var(--text-3); }
    .login-input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px var(--accent-dim);
    }
    .login-btn {
      width:100%;
      padding: 12px;
      background: var(--accent);
      border: none;
      border-radius: var(--radius);
      color: #fff;
      font-size: 13px;
      font-weight: 600;
      letter-spacing: .3px;
      transition: background .2s, transform .15s;
    }
    .login-btn:hover  { background: #3d5ce0; }
    .login-btn:active { transform: scale(.98); }
    .login-error {
      display:flex; align-items:center; gap:8px;
      margin-top: 14px;
      padding: 10px 14px;
      background: var(--red-dim);
      border: 1px solid rgba(248,113,113,.2);
      border-radius: 8px;
      font-size: 12px;
      color: var(--red);
    }

    /* ══════════════════════════════════════════
       INBOX LAYOUT
    ══════════════════════════════════════════ */
    .inbox-layout {
      position: relative; z-index: 1;
      display: grid;
      grid-template-columns: 360px 1fr;
      height: 100vh;
      overflow: hidden;
    }

    /* ── SIDEBAR ── */
    .sidebar {
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      background: var(--surface);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      height: 100vh;
      overflow: hidden;
    }
    .sidebar-head {
      padding: 22px 20px 16px;
      border-bottom: 1px solid var(--border);
      flex-shrink: 0;
    }
    .sidebar-brand {
      display:flex; align-items:center; gap:10px;
      margin-bottom:16px;
    }
    .brand-icon {
      width:32px; height:32px;
      background: var(--accent-dim);
      border: 1px solid rgba(79,110,247,.25);
      border-radius:8px;
      display:flex; align-items:center; justify-content:center;
    }
    .brand-icon svg { width:15px; height:15px; stroke:var(--accent); fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .brand-name { font-size:13px; font-weight:600; color:var(--text); letter-spacing:-.2px; }
    .stats-row { display:flex; gap:8px; }
    .stat-pill {
      flex:1;
      background: var(--surface-2);
      border: 1px solid var(--border);
      border-radius:8px;
      padding: 10px 12px;
      text-align:center;
    }
    .stat-pill .num { display:block; font-size:22px; font-weight:600; letter-spacing:-.5px; }
    .stat-pill .lbl { display:block; font-size:11px; font-weight:500; letter-spacing:.5px; text-transform:uppercase; color:var(--text-3); margin-top:2px; }
    .num-default { color: var(--text); }
    .num-red     { color: var(--amber); }
    .num-green   { color: var(--green); }

    .sidebar-actions {
      padding: 12px 20px;
      border-bottom: 1px solid var(--border);
      display:flex; gap:8px;
      flex-shrink: 0;
    }
    .pill-btn {
      font-size: 11px;
      font-weight: 500;
      padding: 6px 12px;
      border-radius: 999px;
      border: 1px solid var(--border-md);
      color: var(--text-2);
      background: none;
      transition: all .2s;
      letter-spacing: .3px;
    }
    .pill-btn:hover { border-color:var(--border-hi); color:var(--text); background:var(--surface-2); }
    .pill-btn.danger:hover { border-color:rgba(248,113,113,.4); color:var(--red); }

    .filter-row {
      display:flex;
      border-bottom: 1px solid var(--border);
      flex-shrink: 0;
    }
    .filter-tab {
      flex:1; text-align:center;
      padding: 10px 6px;
      font-size:11px; font-weight:500; letter-spacing:.4px;
      color: var(--text-3);
      border-bottom: 2px solid transparent;
      transition: color .2s, border-color .2s;
      text-transform: uppercase;
    }
    .filter-tab:hover { color:var(--text-2); }
    .filter-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
    .unread-dot {
      display:inline-block;
      width:16px; height:16px;
      border-radius:999px;
      background:var(--accent);
      color:#fff;
      font-size:9px; font-weight:700;
      line-height:16px; text-align:center;
      margin-left:5px;
      vertical-align:middle;
    }

    .msg-list { flex:1; overflow-y:auto; }
    .msg-item {
      display:flex; gap:14px; align-items:flex-start;
      padding: 18px 22px;
      border-bottom: 1px solid var(--border);
      cursor: pointer;
      transition: background .15s;
      position: relative;
      text-decoration: none;
    }
    .msg-item:hover  { background:var(--surface-2); }
    .msg-item.active { background:var(--surface-3); }
    .msg-item.active::before {
      content:'';
      position:absolute; left:0; top:0; bottom:0;
      width:3px;
      background:var(--accent);
      border-radius:0 2px 2px 0;
    }
    .msg-avatar {
      width:38px; height:38px; flex-shrink:0;
      border-radius:50%;
      background:var(--surface-3);
      border:1px solid var(--border-md);
      display:flex; align-items:center; justify-content:center;
      font-size:12px; font-weight:600; color:var(--text-2);
      font-family:'DM Mono',monospace;
      letter-spacing:.5px;
    }
    .msg-item.unread .msg-avatar { background:var(--accent-dim); border-color:rgba(79,110,247,.3); color:var(--accent); }
    .msg-info { flex:1; min-width:0; }
    .msg-name-row { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:3px; }
    .msg-name { font-size:14px; font-weight:500; color:var(--text-2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:120px; }
    .msg-item.unread .msg-name { color:var(--text); font-weight:600; }
    .msg-time { font-size:10px; color:var(--text-3); flex-shrink:0; margin-left:6px; white-space:nowrap; }
    .msg-subject { font-size:13px; color:var(--text-3); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:5px; }
    .msg-source-tag {
      display:inline-block;
      font-size:11px; font-weight:500; letter-spacing:.3px;
      padding:3px 9px;
      border-radius:5px;
      background:var(--surface-3);
      color:var(--text-3);
      border:1px solid var(--border);
    }
    .msg-unread-dot {
      position:absolute; top:18px; right:16px;
      width:6px; height:6px; border-radius:50%;
      background:var(--accent);
    }
    .empty-state { padding:48px 20px; text-align:center; color:var(--text-3); font-size:12px; letter-spacing:.5px; }

    .sidebar-footer {
      padding:14px 20px;
      border-top: 1px solid var(--border);
      display:flex; justify-content:space-between; align-items:center;
      flex-shrink:0;
    }
    .footer-user { font-size:11px; color:var(--text-3); }
    .logout-link { font-size:11px; color:var(--text-3); transition:color .2s; }
    .logout-link:hover { color:var(--red); }

    /* ── MAIN PANEL ── */
    .main-panel {
      display: flex;
      flex-direction: column;
      overflow-y: auto;
      height: 100vh;
      background: transparent;
    }

    .msg-empty {
      flex:1; display:flex; flex-direction:column;
      align-items:center; justify-content:center;
      gap:12px; padding:48px;
      color:var(--text-3);
    }
    .msg-empty-icon {
      width:52px; height:52px;
      background:var(--surface-2);
      border:1px solid var(--border);
      border-radius:14px;
      display:flex; align-items:center; justify-content:center;
      margin-bottom:4px;
    }
    .msg-empty-icon svg { width:24px; height:24px; stroke:var(--text-3); fill:none; stroke-width:1.5; stroke-linecap:round; stroke-linejoin:round; }
    .msg-empty p { font-size:13px; }

    /* ── MESSAGE VIEW ── */
    .msg-view {
      padding: 44px 64px;
      width: 100%;
      max-width: none;
    }

    .msg-view-header { margin-bottom:28px; }
    .msg-view-top { display:flex; align-items:flex-start; gap:14px; margin-bottom:20px; }
    .msg-view-avatar {
      width:48px; height:48px; flex-shrink:0;
      border-radius:12px;
      background:var(--accent-dim);
      border:1px solid rgba(79,110,247,.25);
      display:flex; align-items:center; justify-content:center;
      font-size:14px; font-weight:600; color:var(--accent);
      font-family:'DM Mono',monospace; letter-spacing:.5px;
    }
    .msg-view-meta { flex:1; }
    .msg-view-name { font-size:18px; font-weight:600; letter-spacing:-.3px; margin-bottom:4px; }
    .msg-chips { display:flex; flex-wrap:wrap; gap:8px; }
    .chip {
      display:inline-flex; align-items:center; gap:6px;
      font-size:11px; font-weight:400;
      padding:4px 10px;
      border-radius:6px;
      border:1px solid var(--border-md);
      color:var(--text-2);
      background:var(--surface-2);
      letter-spacing:.2px;
    }
    .chip svg { width:12px; height:12px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
    .chip.accent { border-color:rgba(79,110,247,.3); color:var(--accent); background:var(--accent-dim); }
    .chip.green  { border-color:rgba(52,211,153,.3); color:var(--green); background:var(--green-dim); }

    .msg-subject-bar {
      padding:10px 16px;
      background:var(--surface-2);
      border:1px solid var(--border);
      border-radius:8px;
      margin-bottom:20px;
      font-size:13px;
      color:var(--text-2);
    }
    .msg-subject-bar strong { color:var(--text); font-weight:500; }

    .action-bar {
      display:flex; gap:8px; flex-wrap:wrap;
      padding-top:16px;
      border-top:1px solid var(--border);
    }
    .action-btn-sm {
      font-size:12px; font-weight:500;
      padding:7px 14px;
      border-radius:8px;
      border:1px solid var(--border-md);
      color:var(--text-2);
      background:none;
      transition:all .2s;
      display:inline-flex; align-items:center; gap:6px;
    }
    .action-btn-sm svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
    .action-btn-sm:hover { border-color:var(--border-hi); color:var(--text); background:var(--surface-2); }
    .action-btn-sm.primary { border-color:rgba(79,110,247,.4); color:var(--accent); background:var(--accent-dim); }
    .action-btn-sm.primary:hover { background:rgba(79,110,247,.25); }
    .action-btn-sm.danger:hover { border-color:rgba(248,113,113,.4); color:var(--red); background:var(--red-dim); }

    .msg-body-card {
      background: var(--surface-2);
      border:1px solid var(--border);
      border-radius:12px;
      padding:32px 36px;
      margin-bottom:24px;
      line-height:1.9;
      color:rgba(232,234,240,.8);
      white-space:pre-wrap;
      word-break:break-word;
      font-family:'DM Mono',monospace;
      font-size:13px;
      backdrop-filter: blur(8px);
    }

    /* ── BANNERS ── */
    .banner {
      display:flex; align-items:center; gap:10px;
      padding:12px 16px;
      border-radius:8px;
      font-size:12px;
      margin-bottom:20px;
    }
    .banner svg { width:14px; height:14px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
    .banner.success { background:var(--green-dim); border:1px solid rgba(52,211,153,.25); color:var(--green); }
    .banner.error   { background:var(--red-dim);   border:1px solid rgba(248,113,113,.25); color:var(--red);   }

    /* ── REPLY COMPOSER ── */
    .reply-composer {
      background:var(--surface);
      border:1px solid var(--border-md);
      border-radius:14px;
      overflow:hidden;
      display:none;
      margin-bottom:40px;
    }
    .reply-composer.open { display:block; }
    .reply-composer-header {
      display:flex; align-items:center; justify-content:space-between;
      padding:14px 20px;
      border-bottom:1px solid var(--border);
      background:var(--surface-2);
    }
    .reply-composer-title { font-size:12px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; color:var(--text-2); }
    .reply-close-btn {
      width:26px; height:26px;
      display:flex; align-items:center; justify-content:center;
      border:1px solid var(--border-md);
      border-radius:6px;
      background:none;
      color:var(--text-3);
      transition:all .2s;
    }
    .reply-close-btn:hover { border-color:rgba(248,113,113,.4); color:var(--red); }
    .reply-close-btn svg { width:12px; height:12px; stroke:currentColor; fill:none; stroke-width:2.5; stroke-linecap:round; }
    .reply-form { padding:22px; display:flex; flex-direction:column; gap:16px; }
    .reply-field label {
      display:block;
      font-size:10px; font-weight:600; letter-spacing:.6px; text-transform:uppercase;
      color:var(--text-3);
      margin-bottom:6px;
    }
    .reply-field input,
    .reply-field textarea {
      width:100%;
      background:var(--surface-2);
      border:1px solid var(--border-md);
      border-radius:8px;
      color:var(--text);
      font-family:'DM Sans',sans-serif;
      font-size:13px;
      padding:10px 14px;
      outline:none;
      transition:border-color .2s, box-shadow .2s;
      resize:vertical;
    }
    .reply-field input:focus,
    .reply-field textarea:focus {
      border-color:var(--accent);
      box-shadow:0 0 0 3px var(--accent-dim);
    }
    .reply-field textarea { min-height:160px; line-height:1.75; font-family:'DM Mono',monospace; font-size:12px; }
    .reply-field input[readonly] { opacity:.55; cursor:default; }
    .reply-actions { display:flex; gap:10px; align-items:center; }
    .reply-send-btn {
      padding:10px 22px;
      background:var(--accent);
      border:none;
      border-radius:8px;
      color:#fff;
      font-size:13px; font-weight:600;
      display:inline-flex; align-items:center; gap:7px;
      transition:background .2s, transform .15s;
    }
    .reply-send-btn svg { width:14px; height:14px; stroke:#fff; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
    .reply-send-btn:hover  { background:#3d5ce0; }
    .reply-send-btn:active { transform:scale(.98); }
    .reply-cancel-btn {
      padding:10px 18px;
      border:1px solid var(--border-md);
      border-radius:8px;
      background:none;
      color:var(--text-3);
      font-size:13px;
      transition:all .2s;
    }
    .reply-cancel-btn:hover { border-color:rgba(248,113,113,.3); color:var(--red); }

    /* ── RESPONSIVE ── */
    @media (max-width:1100px) {
      .inbox-layout { grid-template-columns: 280px 1fr; }
    }
    @media (max-width:700px) {
      .inbox-layout { grid-template-columns:1fr; height:auto; overflow:auto; }
      .sidebar { position:static; height:auto; max-height:50vh; border-right:none; border-bottom:1px solid var(--border); }
      .main-panel { height:auto; min-height:50vh; }
      .msg-view { padding:24px 20px; }
    }
  </style>
</head>
<body>

<?php if (!$loggedIn): ?>

<div class="bg-mesh"></div>
<div class="login-page">
  <div class="login-card">
    <div class="login-badge">
      <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    </div>
    <h1>Admin Inbox</h1>
    <p>Enter your password to continue.</p>
    <form method="POST">
      <label class="field-label">Password</label>
      <input class="login-input" type="password" name="password" placeholder="••••••••" autofocus autocomplete="current-password"/>
      <button type="submit" class="login-btn">Sign in</button>
      <?php if ($error): ?>
        <div class="login-error">
          <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" fill="none" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php else: ?>

<?php $filter = $_GET['filter'] ?? 'all'; ?>
<div class="bg-mesh"></div>
<div class="inbox-layout">

  <aside class="sidebar">
    <div class="sidebar-head">
      <div class="sidebar-brand">
        <div class="brand-icon">
          <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <span class="brand-name">Inbox</span>
      </div>
      <div class="stats-row">
        <div class="stat-pill">
          <span class="num num-default"><?= $totalCount ?></span>
          <span class="lbl">Total</span>
        </div>
        <div class="stat-pill">
          <span class="num num-red"><?= $unreadCount ?></span>
          <span class="lbl">Unread</span>
        </div>
        <div class="stat-pill">
          <span class="num num-green"><?= $totalCount - $unreadCount ?></span>
          <span class="lbl">Read</span>
        </div>
      </div>
    </div>

    <div class="sidebar-actions">
      <a href="?readall=1" class="pill-btn">Mark all read</a>
      <a href="?deleteread=1" class="pill-btn danger" onclick="return confirm('Delete all read messages?')">Delete read</a>
    </div>

    <div class="filter-row">
      <a href="?" class="filter-tab <?= $filter==='all' ? 'active':'' ?>">All</a>
      <a href="?filter=unread" class="filter-tab <?= $filter==='unread' ? 'active':'' ?>">
        Unread<?php if($unreadCount>0): ?><span class="unread-dot"><?= $unreadCount ?></span><?php endif; ?>
      </a>
      <a href="?filter=read" class="filter-tab <?= $filter==='read' ? 'active':'' ?>">Read</a>
    </div>

    <div class="msg-list">
      <?php if (empty($messages)): ?>
        <div class="empty-state">No messages found.</div>
      <?php else: foreach ($messages as $m):
        $isActive = isset($openMsg) && $openMsg && $openMsg['id'] == $m['id'];
        $isUnread = empty($m['is_read']);
        $initials = getInitials($m['name'] ?? '?');
        $preview  = !empty($m['subject']) ? mb_substr($m['subject'],0,48) : mb_substr($m['message']??'',0,48);
        $dateStr  = !empty($m['created_at']) ? date('M j, Y · g:i A', strtotime($m['created_at'])) : '';
      ?>
        <a href="?id=<?= (int)$m['id'] ?><?= $filter !== 'all' ? '&filter=' . urlencode($filter) : '' ?>"
           class="msg-item <?= $isActive ? 'active':'' ?> <?= $isUnread ? 'unread':'' ?>">
          <div class="msg-avatar"><?= htmlspecialchars($initials) ?></div>
          <div class="msg-info">
            <div class="msg-name-row">
              <span class="msg-name"><?= htmlspecialchars($m['name'] ?? 'Unknown') ?></span>
              <span class="msg-time"><?= $dateStr ?></span>
            </div>
            <div class="msg-subject"><?= htmlspecialchars($preview) ?>…</div>
            <span class="msg-source-tag"><?= htmlspecialchars(displaySourceLabel($m)) ?></span>
          </div>
          <?php if($isUnread): ?><div class="msg-unread-dot"></div><?php endif; ?>
        </a>
      <?php endforeach; endif; ?>
    </div>

    <div class="sidebar-footer">
      <span class="footer-user">Signed in as admin</span>
      <a href="?logout=1" class="logout-link">Sign out →</a>
    </div>
  </aside>

  <main class="main-panel">
    <?php if ($openMsg): ?>
      <div class="msg-view">

        <?php if (isset($_GET['replied'])): ?>
          <div class="banner success">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            Reply sent successfully to <?= htmlspecialchars($openMsg['email'] ?? '') ?>.
          </div>
        <?php endif; ?>

        <?php if ($replyStatus === 'error'): ?>
          <div class="banner error">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Failed to send. Check your Gmail App Password in db.php.
          </div>
        <?php endif; ?>

        <div class="msg-view-header">
          <div class="msg-view-top">
            <div class="msg-view-avatar"><?= htmlspecialchars(getInitials($openMsg['name'] ?? '?')) ?></div>
            <div class="msg-view-meta">
              <div class="msg-view-name"><?= htmlspecialchars($openMsg['name'] ?? 'Unknown') ?></div>
              <div class="msg-chips">
                <span class="chip">
                  <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                  <?= htmlspecialchars($openMsg['email'] ?? '') ?>
                </span>
                <?php if (!empty($openMsg['service'])): ?>
                  <span class="chip accent"><?= htmlspecialchars($openMsg['service']) ?></span>
                <?php endif; ?>
                <span class="chip"><?= htmlspecialchars(displaySourceLabel($openMsg)) ?></span>
                <?php if (!empty($openMsg['created_at'])): ?>
                  <span class="chip">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <?= date('F j, Y · g:i A', strtotime($openMsg['created_at'])) ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <?php if (!empty($openMsg['subject'])): ?>
            <div class="msg-subject-bar">
              <strong>Subject:</strong> <?= htmlspecialchars($openMsg['subject']) ?>
            </div>
          <?php endif; ?>

          <div class="action-bar">
            <button type="button" class="action-btn-sm primary" onclick="toggleReply()">
              <svg viewBox="0 0 24 24"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
              Reply
            </button>

            <?php if (!empty($openMsg['is_read'])): ?>
              <a href="?unread=<?= (int)$openMsg['id'] ?>" class="action-btn-sm">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/></svg>
                Mark unread
              </a>
            <?php else: ?>
              <a href="?read=<?= (int)$openMsg['id'] ?>" class="action-btn-sm">
                <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                Mark read
              </a>
            <?php endif; ?>

            <a href="?delete=<?= (int)$openMsg['id'] ?>" class="action-btn-sm danger"
               onclick="return confirm('Delete this message permanently?')">
              <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
              Delete
            </a>
          </div>
        </div>

        <div class="msg-body-card"><?= htmlspecialchars($openMsg['message'] ?? '') ?></div>

        <div class="reply-composer" id="replyComposer">
          <div class="reply-composer-header">
            <span class="reply-composer-title">Compose Reply</span>
            <button type="button" class="reply-close-btn" onclick="toggleReply()">
              <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="reply-form">
            <form method="POST" style="display:contents;">
              <input type="hidden" name="msg_id" value="<?= (int)$openMsg['id'] ?>">
              <input type="hidden" name="reply_to_email" value="<?= htmlspecialchars($openMsg['email'] ?? '') ?>">
              <input type="hidden" name="reply_to_name" value="<?= htmlspecialchars($openMsg['name'] ?? '') ?>">

              <div class="reply-field">
                <label>To</label>
                <input type="text"
                       value="<?= htmlspecialchars(($openMsg['name']??'') . ' <' . ($openMsg['email']??'') . '>') ?>"
                       readonly>
              </div>

              <div class="reply-field">
                <label>Subject</label>
                <input type="text" name="reply_subject"
                       value="<?= htmlspecialchars(buildReplySubject($openMsg)) ?>"
                       placeholder="Subject…">
              </div>

              <div class="reply-field">
                <label>Message</label>
                <textarea name="reply_body" placeholder="Write your reply here…"><?php
                  $createdAt = !empty($openMsg['created_at'])
                      ? date('F j, Y \a\t g:i A', strtotime($openMsg['created_at']))
                      : date('F j, Y \a\t g:i A');
                  $senderName = $openMsg['name'] ?? 'Client';
                  $originalMessage = $openMsg['message'] ?? '';
                  $quoted = "\n\n\n---\nOn " . $createdAt .
                            ", " . $senderName . " wrote:\n" .
                            implode("\n", array_map(fn($l) => '> ' . $l, explode("\n", $originalMessage)));
                  echo htmlspecialchars($quoted);
                ?></textarea>
              </div>

              <div class="reply-actions">
                <button type="submit" class="reply-send-btn">
                  <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                  Send reply
                </button>
                <button type="button" class="reply-cancel-btn" onclick="toggleReply()">Cancel</button>
              </div>
            </form>
          </div>
        </div>

      </div>

    <?php else: ?>
      <div class="msg-empty">
        <div class="msg-empty-icon">
          <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <p>Select a message to read it</p>
      </div>
    <?php endif; ?>
  </main>

</div>
<?php endif; ?>

<script>
  function toggleReply() {
    const c = document.getElementById('replyComposer');
    if (!c) return;
    c.classList.toggle('open');
    if (c.classList.contains('open')) {
      const ta = c.querySelector('textarea');
      if (ta) { ta.focus(); ta.setSelectionRange(0,0); ta.scrollTop=0; }
      setTimeout(() => c.scrollIntoView({ behavior:'smooth', block:'nearest' }), 50);
    }
  }
  <?php if ($replyStatus === 'error'): ?>
  document.addEventListener('DOMContentLoaded', () => {
    const c = document.getElementById('replyComposer');
    if (c) c.classList.add('open');
  });
  <?php endif; ?>
</script>
</body>
</html>