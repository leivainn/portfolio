<?php
require_once 'db.php';

$form_success = false;
$form_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {

    $name    = trim(strip_tags($_POST['name'] ?? ''));
    $email   = trim(strip_tags($_POST['email'] ?? ''));
    $service = trim(strip_tags($_POST['service'] ?? ''));
    $message = trim(strip_tags($_POST['message'] ?? ''));
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '';

    // Validation
    if ($name === '' || $email === '' || $message === '') {

        $form_error = 'Please fill in all required fields.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $form_error = 'Please enter a valid email address.';

    } else {

        // Insert into Supabase
        $saved = insertMessage(
            $name,
            $email,
            $service,
            $message,
            $ip
        );

        if ($saved) {

            $form_success = true;

        } else {

            $form_error = 'Failed to send message.';

        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>LAURENCE IVAN NAMOC</title>
  <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;700;900&family=Space+Mono:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
  <style>
    :root {
      --cyan: #00f5ff;
      --pink: #ff2d78;
      --glass-bg: rgba(255, 255, 255, 0.06);
      --glass-border: rgba(255, 255, 255, 0.15);
      --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
      --text: #f0f0f0;
      --muted: rgba(255,255,255,0.5);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      font-family: 'Space Mono', monospace;
      background: #050a14;
      color: var(--text);
      overflow-x: hidden;
    }

    .bg-layer {
      position: fixed; inset: 0; z-index: 0;
      background:
        radial-gradient(ellipse 80% 60% at 20% 10%, rgba(0,245,255,0.12) 0%, transparent 60%),
        radial-gradient(ellipse 60% 70% at 80% 80%, rgba(255,45,120,0.10) 0%, transparent 55%),
        linear-gradient(135deg, #050a14 0%, #0a1628 50%, #050a14 100%);
    }
    .bg-grid {
      position: fixed; inset: 0; z-index: 0;
      background-image:
        linear-gradient(rgba(0,245,255,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(0,245,255,0.04) 1px, transparent 1px);
      background-size: 60px 60px;
      animation: gridDrift 20s linear infinite;
    }
    @keyframes gridDrift { 0% { transform: translateY(0); } 100% { transform: translateY(60px); } }

    .orb { position: fixed; border-radius: 50%; filter: blur(80px); opacity: 0.25; pointer-events: none; z-index: 0; animation: orbFloat 12s ease-in-out infinite alternate; }
    .orb-1 { width: 500px; height: 500px; background: #00f5ff; top: -150px; left: -100px; animation-duration: 14s; }
    .orb-2 { width: 400px; height: 400px; background: #ff2d78; bottom: -100px; right: -80px; animation-duration: 10s; }
    .orb-3 { width: 300px; height: 300px; background: #7b2fff; top: 40%; left: 60%; animation-duration: 16s; }
    @keyframes orbFloat { 0% { transform: translate(0,0) scale(1); } 100% { transform: translate(30px,40px) scale(1.1); } }

    nav {
      position: fixed; top: 0; left: 0; width: 100%; z-index: 100;
      padding: 22px 50px;
      display: flex; justify-content: space-between; align-items: center;
      backdrop-filter: blur(16px);
      background: rgba(5,10,20,0.5);
      border-bottom: 1px solid var(--glass-border);
    }
    .nav-logo {
      font-family: 'Orbitron', sans-serif; font-size: 14px; font-weight: 700;
      letter-spacing: 4px; color: var(--cyan); text-decoration: none;
      text-shadow: 0 0 20px rgba(0,245,255,0.6); cursor: pointer; transition: 0.3s;
    }
    .nav-logo:hover { color: #fff; text-shadow: 0 0 30px rgba(0,245,255,1); }
    .nav-links { display: flex; gap: 36px; list-style: none; }
    .nav-links a {
      font-family: 'Space Mono', monospace; font-size: 11px; letter-spacing: 3px;
      text-transform: uppercase; color: var(--muted); text-decoration: none;
      position: relative; transition: color 0.3s;
    }
    .nav-links a::after {
      content: ''; position: absolute; bottom: -4px; left: 0;
      width: 0; height: 1px; background: var(--cyan); transition: width 0.3s ease;
    }
    .nav-links a:hover { color: var(--cyan); }
    .nav-links a:hover::after { width: 100%; }

    section {
      position: relative; z-index: 1; min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      padding: 120px 50px 80px;
    }

    #home { flex-direction: column; text-align: center; }

    .hero-eyebrow {
      font-family: 'Space Mono', monospace; font-size: 11px; letter-spacing: 6px;
      color: var(--cyan); text-transform: uppercase;
      opacity: 0; animation: fadeUp 0.8s ease forwards 0.3s;
    }
    .hero-name {
      font-family: 'Orbitron', sans-serif; font-size: clamp(36px, 7vw, 90px);
      font-weight: 900; line-height: 1; margin: 20px 0 10px;
      background: linear-gradient(135deg, #fff 30%, var(--cyan) 70%);
      -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
      opacity: 0; animation: fadeUp 0.8s ease forwards 0.6s;
    }
    .hero-tagline {
      font-size: 15px; color: var(--muted); letter-spacing: 2px; margin-bottom: 60px;
      opacity: 0; animation: fadeUp 0.8s ease forwards 0.9s;
    }
    .hero-nav-cards {
      display: flex; gap: 20px; flex-wrap: wrap; justify-content: center;
      opacity: 0; animation: fadeUp 0.8s ease forwards 1.2s;
    }

    .glass-card {
      background: var(--glass-bg);
      border: 1px solid var(--glass-border);
      border-radius: 16px;
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      box-shadow: var(--glass-shadow);
      transition: transform 0.35s ease, border-color 0.35s ease, box-shadow 0.35s ease;
    }
    .glass-card:hover {
      transform: translateY(-6px);
      border-color: var(--cyan);
      box-shadow: 0 16px 48px rgba(0,245,255,0.15), var(--glass-shadow);
    }

    .nav-card {
      padding: 28px 36px; cursor: pointer; text-decoration: none;
      display: flex; flex-direction: column; align-items: center; gap: 10px; min-width: 160px;
    }
    .nav-card .card-label { font-family: 'Orbitron', sans-serif; font-size: 11px; letter-spacing: 3px; color: var(--text); text-transform: uppercase; }
    .nav-card .card-sub { font-size: 10px; color: var(--muted); letter-spacing: 1px; }

    .profile-card-wrapper {
      display: flex; justify-content: center; margin-bottom: 50px;
      opacity: 0; animation: profileReveal 1s cubic-bezier(0.22, 1, 0.36, 1) forwards 1.5s;
    }
    @keyframes profileReveal {
      0%   { opacity: 0; transform: translateY(50px) scale(0.9); filter: blur(8px); }
      60%  { opacity: 1; filter: blur(0px); }
      100% { opacity: 1; transform: translateY(0) scale(1); filter: blur(0px); }
    }

    .profile-card {
      position: relative; width: 440px; padding: 44px 36px 36px; border-radius: 28px;
      background: rgba(255,255,255,0.05); border: 1px solid rgba(0,245,255,0.25);
      backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
      box-shadow: 0 0 80px rgba(0,245,255,0.08), 0 24px 60px rgba(0,0,0,0.5);
      display: flex; flex-direction: column; align-items: center; gap: 0; overflow: hidden;
      transition: transform 0.4s ease, box-shadow 0.4s ease, border-color 0.4s ease;
    }
    .profile-card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: 0 0 100px rgba(0,245,255,0.18), 0 32px 80px rgba(0,0,0,0.6);
      border-color: rgba(0,245,255,0.5);
    }
    .profile-card::before {
      content: ''; position: absolute; top: 0; left: -100%;
      width: 100%; height: 2px;
      background: linear-gradient(90deg, transparent, var(--cyan), var(--pink), transparent);
      animation: beamSlide 3s ease-in-out infinite 2.5s;
    }
    @keyframes beamSlide { 0%{left:-100%} 50%{left:100%} 100%{left:100%} }
    .profile-card::after {
      content: ''; position: absolute; inset: 0; border-radius: 28px;
      background: radial-gradient(ellipse 60% 40% at 50% 0%, rgba(0,245,255,0.08) 0%, transparent 70%);
      pointer-events: none;
    }

    .profile-avatar-ring {
      position: relative; width: 150px; height: 150px; margin-bottom: 22px;
      animation: avatarPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards 2.2s;
      opacity: 0; transform: scale(0);
    }
    @keyframes avatarPop {
      0%   { opacity: 0; transform: scale(0) rotate(-15deg); }
      100% { opacity: 1; transform: scale(1) rotate(0deg); }
    }
    .avatar-ring-svg { position: absolute; inset: -8px; width: calc(100% + 16px); height: calc(100% + 16px); animation: spinRing 6s linear infinite; }
    @keyframes spinRing { to { transform: rotate(360deg); } }
    .avatar-inner {
      width: 150px; height: 150px; border-radius: 50%;
      background: linear-gradient(135deg, #0a2040, #0d3060);
      border: 2px solid rgba(0,245,255,0.3);
      display: flex; align-items: center; justify-content: center;
      font-family: 'Orbitron', sans-serif; font-size: 36px; font-weight: 900;
      color: var(--cyan); text-shadow: 0 0 20px rgba(0,245,255,0.6);
      overflow: hidden; position: relative; z-index: 1;
    }
    .avatar-inner img { width: 100%; height: 100%; object-fit: cover; object-position: center top; border-radius: 50%; display: block; }

    .profile-name { font-family: 'Orbitron', sans-serif; font-size: 20px; font-weight: 700; color: #fff; letter-spacing: 2px; text-align: center; margin-bottom: 6px; opacity: 0; animation: fadeUp 0.5s ease forwards 2.4s; }
    .profile-title { font-size: 11px; letter-spacing: 3px; color: var(--cyan); text-transform: uppercase; margin-bottom: 22px; opacity: 0; animation: fadeUp 0.5s ease forwards 2.55s; }
    .profile-divider { width: 100%; height: 1px; background: linear-gradient(90deg, transparent, rgba(0,245,255,0.3), transparent); margin-bottom: 22px; opacity: 0; animation: fadeIn 0.5s ease forwards 2.7s; }
    @keyframes fadeIn { to { opacity: 1; } }

    .profile-stats { display: flex; gap: 0; width: 100%; margin-bottom: 22px; opacity: 0; animation: fadeUp 0.5s ease forwards 2.8s; }
    .stat-item { flex: 1; text-align: center; padding: 14px 8px; }
    .stat-item + .stat-item { border-left: 1px solid rgba(255,255,255,0.08); }
    .stat-num { font-family: 'Orbitron', sans-serif; font-size: 22px; font-weight: 700; color: var(--cyan); display: block; }
    .stat-label { font-size: 9px; letter-spacing: 2px; color: var(--muted); text-transform: uppercase; margin-top: 4px; display: block; }

    .profile-badge {
      display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 999px;
      background: rgba(0,255,136,0.1); border: 1px solid rgba(0,255,136,0.3);
      font-size: 10px; letter-spacing: 2px; color: #00ff88; text-transform: uppercase; margin-bottom: 26px;
      opacity: 0; animation: fadeUp 0.5s ease forwards 2.95s;
    }
    .badge-dot { width: 7px; height: 7px; border-radius: 50%; background: #00ff88; box-shadow: 0 0 8px #00ff88; animation: blink 1.5s ease-in-out infinite; }

    .profile-socials { display: flex; gap: 14px; opacity: 0; animation: fadeUp 0.5s ease forwards 3.1s; }
    .social-btn {
      width: 50px; height: 50px; border-radius: 14px;
      background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);
      display: flex; align-items: center; justify-content: center;
      cursor: pointer; text-decoration: none; transition: all 0.3s; position: relative; overflow: hidden;
    }
    .social-btn::before { content: ''; position: absolute; inset: 0; opacity: 0; border-radius: 14px; transition: opacity 0.3s; }
    .social-btn.instagram::before { background: linear-gradient(135deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888); }
    .social-btn.facebook::before  { background: linear-gradient(135deg,#1877f2,#0a5cbf); }
    .social-btn.github::before    { background: linear-gradient(135deg,#333,#666); }
    .social-btn.twitter::before   { background: linear-gradient(135deg,#1da1f2,#0c78c0); }
    .social-btn:hover::before { opacity: 1; }
    .social-btn:hover { transform: translateY(-4px); border-color: transparent; box-shadow: 0 8px 24px rgba(0,0,0,0.4); }
    .social-btn svg { position: relative; z-index: 1; transition: transform 0.3s; }
    .social-btn:hover svg { transform: scale(1.15); }

    /* ── ABOUT ── */
    #about { justify-content: flex-start; align-items: flex-start; max-width: 1100px; margin: 0 auto; gap: 60px; }
    .section-heading { font-family: 'Orbitron', sans-serif; font-size: 11px; letter-spacing: 6px; color: var(--cyan); text-transform: uppercase; margin-bottom: 20px; }
    .section-title { font-family: 'Orbitron', sans-serif; font-size: clamp(28px, 4vw, 52px); font-weight: 700; line-height: 1.1; margin-bottom: 30px; }
    .section-title span { color: var(--cyan); }
    .about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; width: 100%; max-width: 1000px; margin: 0 auto; }
    .about-text { display: flex; flex-direction: column; justify-content: center; }
    .about-text p { font-size: 14px; line-height: 1.9; color: rgba(255,255,255,0.7); margin-bottom: 20px; }
    .skills-list { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
    .skill-tag { padding: 6px 16px; border-radius: 999px; border: 1px solid rgba(0,245,255,0.3); font-size: 10px; letter-spacing: 2px; color: var(--cyan); text-transform: uppercase; background: rgba(0,245,255,0.05); }
    .about-card-wrap { display: flex; flex-direction: column; gap: 16px; }
    .info-card { padding: 24px 28px; }
    .info-card .label { font-size: 10px; letter-spacing: 3px; color: var(--muted); text-transform: uppercase; margin-bottom: 6px; }
    .info-card .value { font-family: 'Orbitron', sans-serif; font-size: 18px; font-weight: 600; color: var(--text); }

    /* ── PROJECTS ── */
    #projects { flex-direction: column; max-width: 1200px; margin: 0 auto; width: 100%; }
    .projects-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; width: 100%; }
    .project-card { border-radius: 20px; overflow: hidden; position: relative; cursor: pointer; text-decoration: none; display: block; background: var(--glass-bg); border: 1px solid var(--glass-border); backdrop-filter: blur(20px); transition: transform 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease; }
    .project-card:hover { transform: translateY(-8px); border-color: var(--cyan); box-shadow: 0 20px 60px rgba(0,245,255,0.15), var(--glass-shadow); }
    .project-img-wrap { position: relative; width: 100%; aspect-ratio: 16/10; overflow: hidden; background: linear-gradient(135deg, #0a1628, #0d2040); }
    .project-img-wrap img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.5s ease; }
    .project-card:hover .project-img-wrap img { transform: scale(1.07); }
    .project-overlay { position: absolute; inset: 0; background: linear-gradient(180deg, transparent 30%, rgba(5,10,20,0.92) 100%); opacity: 0; transition: opacity 0.4s ease; display: flex; align-items: flex-end; padding: 20px; }
    .project-card:hover .project-overlay { opacity: 1; }
    .overlay-btn { font-family: 'Orbitron', sans-serif; font-size: 10px; letter-spacing: 3px; color: var(--cyan); border: 1px solid var(--cyan); padding: 8px 18px; border-radius: 999px; text-transform: uppercase; background: rgba(0,245,255,0.1); }
    .project-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, var(--cyan), var(--pink)); opacity: 0; transition: opacity 0.3s; z-index: 2; }
    .project-card:hover::before { opacity: 1; }
    .project-info { padding: 22px 24px 24px; }
    .project-tags { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
    .project-tag { font-size: 9px; letter-spacing: 2px; color: var(--cyan); text-transform: uppercase; padding: 4px 10px; border-radius: 999px; border: 1px solid rgba(0,245,255,0.25); background: rgba(0,245,255,0.05); }
    .project-name { font-family: 'Orbitron', sans-serif; font-size: 14px; font-weight: 700; color: var(--text); margin-bottom: 8px; letter-spacing: 1px; }
    .project-desc { font-size: 12px; line-height: 1.8; color: var(--muted); }
    .project-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 16px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.07); }
    .project-year { font-size: 10px; letter-spacing: 2px; color: var(--muted); }
    .project-arrow { width: 32px; height: 32px; border-radius: 50%; border: 1px solid rgba(0,245,255,0.3); display: flex; align-items: center; justify-content: center; color: var(--cyan); font-size: 14px; transition: background 0.3s, border-color 0.3s; }
    .project-card:hover .project-arrow { background: rgba(0,245,255,0.1); border-color: var(--cyan); }

    /* ── SERVICES ── */
    #services { flex-direction: column; max-width: 1100px; margin: 0 auto; width: 100%; }
    .services-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; width: 100%; }
    .service-card { padding: 36px 30px; position: relative; overflow: hidden; }
    .service-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, var(--cyan), var(--pink)); opacity: 0; transition: opacity 0.3s; }
    .service-card:hover::before { opacity: 1; }
    .service-icon { font-size: 36px; margin-bottom: 20px; display: block; }
    .service-name { font-family: 'Orbitron', sans-serif; font-size: 13px; letter-spacing: 2px; margin-bottom: 12px; color: var(--text); }
    .service-desc { font-size: 12px; line-height: 1.8; color: var(--muted); }
    .service-price { display: inline-block; margin-top: 20px; padding: 6px 16px; border-radius: 999px; background: rgba(0,245,255,0.1); border: 1px solid rgba(0,245,255,0.3); font-size: 11px; color: var(--cyan); letter-spacing: 1px; }

    /* ── CONTACT ── */
    #contact { flex-direction: column; max-width: 800px; margin: 0 auto; width: 100%; }
    .contact-card { padding: 50px 60px; width: 100%; }
    .contact-form { display: flex; flex-direction: column; gap: 20px; margin-top: 36px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { display: flex; flex-direction: column; gap: 8px; }
    .form-group label { font-size: 10px; letter-spacing: 3px; color: var(--muted); text-transform: uppercase; }
    .form-group input, .form-group textarea, .form-group select {
      background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.12);
      border-radius: 10px; padding: 14px 18px; color: var(--text);
      font-family: 'Space Mono', monospace; font-size: 13px; outline: none;
      transition: border-color 0.3s, box-shadow 0.3s; resize: none;
    }
    .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
      border-color: var(--cyan); box-shadow: 0 0 0 3px rgba(0,245,255,0.08);
    }
    .form-group select option { background: #0a1628; }
    .btn-submit {
      padding: 16px 40px; border: none; border-radius: 12px;
      background: linear-gradient(135deg, var(--cyan), #0080ff);
      color: #000; font-family: 'Orbitron', sans-serif; font-size: 12px; font-weight: 700;
      letter-spacing: 3px; text-transform: uppercase; cursor: pointer;
      transition: transform 0.2s, box-shadow 0.3s; align-self: flex-start;
    }
    .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(0,245,255,0.35); }

    /* ── ALERT MESSAGES ── */
    .alert {
      padding: 16px 22px; border-radius: 12px; font-size: 13px; letter-spacing: 1px;
      margin-bottom: 10px; display: flex; align-items: center; gap: 10px;
    }
    .alert-success {
      background: rgba(0,255,136,0.1); border: 1px solid rgba(0,255,136,0.4); color: #00ff88;
    }
    .alert-error {
      background: rgba(255,45,120,0.1); border: 1px solid rgba(255,45,120,0.4); color: #ff2d78;
    }

    .contact-links { display: flex; gap: 16px; margin-top: 40px; flex-wrap: wrap; }
    .contact-link {
      display: flex; align-items: center; gap: 10px; padding: 12px 22px; border-radius: 12px;
      text-decoration: none; font-size: 11px; letter-spacing: 2px; color: var(--text);
      transition: all 0.3s; position: relative; overflow: hidden;
    }
    .contact-link::before { content: ''; position: absolute; inset: 0; opacity: 0; border-radius: 12px; transition: opacity 0.3s; }
    .contact-link.email::before   { background: linear-gradient(135deg, rgba(0,245,255,0.2), rgba(0,128,255,0.2)); }
    .contact-link.instagram::before { background: linear-gradient(135deg, rgba(240,148,51,0.2), rgba(220,39,67,0.2)); }
    .contact-link.facebook::before  { background: linear-gradient(135deg, rgba(24,119,242,0.2), rgba(10,92,191,0.2)); }
    .contact-link.github::before    { background: linear-gradient(135deg, rgba(110,84,148,0.2), rgba(60,40,80,0.2)); }
    .contact-link.twitter::before   { background: linear-gradient(135deg, rgba(29,161,242,0.2), rgba(12,120,192,0.2)); }
    .contact-link:hover::before { opacity: 1; }
    .contact-link:hover { transform: translateY(-3px); color: #fff; }
    .contact-link svg { position: relative; z-index: 1; flex-shrink: 0; transition: transform 0.3s; }
    .contact-link:hover svg { transform: scale(1.15); }
    .contact-link span { position: relative; z-index: 1; }

    /* ── SHARED ── */
    .section-divider { display: flex; align-items: center; gap: 16px; margin-bottom: 60px; width: 100%; }
    .divider-line { flex: 1; height: 1px; background: linear-gradient(90deg, transparent, rgba(0,245,255,0.3), transparent); }

    .commission-badge {
      display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; border-radius: 999px;
      border: 1px solid rgba(0,245,255,0.4); background: rgba(0,245,255,0.08);
      font-size: 11px; letter-spacing: 3px; color: var(--cyan); text-transform: uppercase;
      margin-bottom: 30px; animation: pulse 3s ease-in-out infinite;
    }
    .commission-dot { width: 8px; height: 8px; border-radius: 50%; background: #00ff88; box-shadow: 0 0 10px #00ff88; animation: blink 1.5s ease-in-out infinite; }

    @keyframes blink  { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
    @keyframes pulse  { 0%, 100% { box-shadow: 0 0 0 0 rgba(0,245,255,0.2); } 50% { box-shadow: 0 0 0 10px rgba(0,245,255,0); } }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

    .reveal { opacity: 0; transform: translateY(40px); transition: opacity 0.8s ease, transform 0.8s ease; }
    .reveal.visible { opacity: 1; transform: translateY(0); }

    footer { position: relative; z-index: 1; text-align: center; padding: 40px; border-top: 1px solid var(--glass-border); font-size: 11px; letter-spacing: 2px; color: var(--muted); }
    footer span { color: var(--cyan); }

    @media (max-width: 900px) { .projects-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 768px) {
      nav { padding: 18px 24px; }
      .nav-links { gap: 20px; }
      .nav-links a { font-size: 9px; letter-spacing: 2px; }
      section { padding: 100px 24px 60px; }
      .about-grid { grid-template-columns: 1fr; }
      .services-grid { grid-template-columns: 1fr; }
      .projects-grid { grid-template-columns: 1fr; }
      .form-row { grid-template-columns: 1fr; }
      .contact-card { padding: 30px 24px; }
      .profile-card { width: 100%; max-width: 420px; }
    }
  </style>
</head>
<body>

  <div class="bg-layer"></div>
  <div class="bg-grid"></div>
  <div class="orb orb-1"></div>
  <div class="orb orb-2"></div>
  <div class="orb orb-3"></div>

  <nav>
    <a class="nav-logo" href="#home">L·I·N</a>
    <ul class="nav-links">
      <li><a href="#home">Home</a></li>
      <li><a href="#about">About</a></li>
      <li><a href="#projects">Projects</a></li>
      <li><a href="#services">Services</a></li>
      <li><a href="#contact">Contact</a></li>
    </ul>
  </nav>

  <!-- ── HERO ── -->
  <section id="home">
    <div class="commission-badge">
      <span class="commission-dot"></span>
      Open for Commissions
    </div>
    <p class="hero-eyebrow">Creative Developer &amp; Designer</p>
    <h1 class="hero-name">LAURENCE<br>IVAN NAMOC</h1>

    <div class="profile-card-wrapper">
      <div class="profile-card">
        <div class="profile-avatar-ring">
          <svg class="avatar-ring-svg" viewBox="0 0 166 166" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="83" cy="83" r="78" stroke="url(#ringGrad)" stroke-width="2" stroke-dasharray="8 6" stroke-linecap="round"/>
            <circle cx="83" cy="5" r="4" fill="#00f5ff" opacity="0.9"/>
            <circle cx="83" cy="161" r="3" fill="#ff2d78" opacity="0.7"/>
            <defs>
              <linearGradient id="ringGrad" x1="0" y1="0" x2="166" y2="166" gradientUnits="userSpaceOnUse">
                <stop offset="0%" stop-color="#00f5ff"/>
                <stop offset="50%" stop-color="#ff2d78"/>
                <stop offset="100%" stop-color="#7b2fff"/>
              </linearGradient>
            </defs>
          </svg>
          <div class="avatar-inner">
            <img src="profile1.png" alt="Laurence Ivan Namoc"
                 onerror="this.style.display='none'; this.parentElement.innerHTML='<span style=\'font-family:Orbitron,sans-serif;font-size:36px;font-weight:900;color:#00f5ff;text-shadow:0 0 20px rgba(0,245,255,0.6)\'>LIN</span>'">
          </div>
        </div>
        <div class="profile-name">LAURENCE IVAN</div>
        <div class="profile-title">Creative Developer · Designer</div>
        <div class="profile-divider"></div>
        <div class="profile-stats">
          <div class="stat-item"><span class="stat-num">3+</span><span class="stat-label">Yrs Exp</span></div>
          <div class="stat-item"><span class="stat-num">20 	 	+</span><span class="stat-label">Projects</span></div>
          <div class="stat-item"><span class="stat-num">🇵🇭</span><span class="stat-label">Based In</span></div>
        </div>
        <div class="profile-badge"><span class="badge-dot"></span>Available for Work</div>
        <div class="profile-socials">
          <a class="social-btn instagram" href="https://www.instagram.com/leivainn?igsh=MTNiZ3h4ZHFud2ZieQ%3D%3D" target="_blank" title="Instagram">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><rect x="2" y="2" width="20" height="20" rx="6" stroke="white" stroke-width="1.6"/><circle cx="12" cy="12" r="4.5" stroke="white" stroke-width="1.6"/><circle cx="17.5" cy="6.5" r="1.2" fill="white"/></svg>
          </a>
          <a class="social-btn facebook" href="https://www.facebook.com/laurence.ivan.naparate.namoc.2025?rdid=n94zsslM2AsVs5PM&share_url=https%3A%2F%2Fwww.facebook.com%2Fshare%2F18WeyZ44hh%2F#" target="_blank" title="Facebook">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="white"><path d="M18 2H15C13.6739 2 12.4021 2.52678 11.4645 3.46447C10.5268 4.40215 10 5.67392 10 7V10H7V14H10V22H14V14H17L18 10H14V7C14 6.73478 14.1054 6.48043 14.2929 6.29289C14.4804 6.10536 14.7348 6 15 6H18V2Z"/></svg>
          </a>
          <a class="social-btn github" href="https://github.com/leivainn" target="_blank" title="GitHub">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="white"><path d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
          </a>
        </div>
      </div>
    </div>

    <div class="hero-nav-cards">
      <a class="glass-card nav-card" href="#about"><span class="card-label">About</span><span class="card-sub">Who I am</span></a>
      <a class="glass-card nav-card" href="#projects"><span class="card-label">Projects</span><span class="card-sub">My work</span></a>
      <a class="glass-card nav-card" href="#services"><span class="card-label">Services</span><span class="card-sub">What I offer</span></a>
      <a class="glass-card nav-card" href="#contact"><span class="card-label">Contact</span><span class="card-sub">Let's work</span></a>
    </div>
  </section>

  <!-- ── ABOUT ── -->
  <section id="about">
    <div class="about-grid">
      <div class="about-text reveal">
        <div class="section-divider">
          <div class="divider-line"></div>
          <p class="section-heading">About Me</p>
          <div class="divider-line"></div>
        </div>
        <h2 class="section-title">Crafting <span>Digital</span><br>Experiences</h2>
        <p>Hey — I'm Laurence Ivan, a creative developer based in the Philippines. I blend design and code to build interfaces that feel alive, look sharp, and actually work.</p>
        <p>I'm passionate about visual storytelling, interactive experiences, and the kind of attention to detail that makes people say <em>"wait, how did they do that?"</em></p>
        <div class="skills-list">
          <span class="skill-tag">UI / UX</span>
          <span class="skill-tag">Web Dev</span>
          <span class="skill-tag">PHP</span>
          <span class="skill-tag">HTML / CSS</span>
          <span class="skill-tag">JavaScript</span>
          <span class="skill-tag">Graphic Design</span>
          <span class="skill-tag">Branding</span>
        </div>
      </div>
      <div class="about-card-wrap reveal" style="transition-delay:0.2s">
        <div class="glass-card info-card"><div class="label">Status</div><div class="value" style="color:#00ff88">Available for Work ✓</div></div>
        <div class="glass-card info-card"><div class="label">Based In</div><div class="value">Philippines 🌏</div></div>
        <div class="glass-card info-card"><div class="label">Experience</div><div class="value">Creative Developer</div></div>
        <div class="glass-card info-card"><div class="label">Specialty</div><div class="value">UI Design &amp; Web Build</div></div>
      </div>
    </div>
  </section>

  <!-- ── PROJECTS ── -->
  <section id="projects">
    <div class="section-divider reveal"><div class="divider-line"></div><p class="section-heading">Recent Work</p><div class="divider-line"></div></div>
    <h2 class="section-title reveal" style="text-align:center; margin-bottom:50px">Featured <span>Projects</span></h2>
    <div class="projects-grid">
      <a class="project-card reveal" href="#" target="_blank" style="transition-delay:0s">
        <div class="project-img-wrap"><img src="image/stargen.png" alt="Project 1" onerror="this.style.display='none'; this.parentElement.style.background='linear-gradient(135deg,#0a2040,#0d1f3c)'"><div class="project-overlay"><span class="overlay-btn">View Project →</span></div></div>
        <div class="project-info"><div class="project-tags"><span class="project-tag">UI Design</span><span class="project-tag">Web Dev</span></div><div class="project-name">Stargen Pharmacy</div><p class="project-desc">Short description of what this project is about.</p><div class="project-footer"><span class="project-year">2025</span><span class="project-arrow">→</span></div></div>
      </a>
      <a class="project-card reveal" href="#" target="_blank" style="transition-delay:0.1s">
        <div class="project-img-wrap"><img src="project2.jpg" alt="Project 2" onerror="this.style.display='none'; this.parentElement.style.background='linear-gradient(135deg,#1a0a30,#2d0a50)'"><div class="project-overlay"><span class="overlay-btn">View Project →</span></div></div>
        <div class="project-info"><div class="project-tags"><span class="project-tag">Branding</span><span class="project-tag">Identity</span></div><div class="project-name">Project Title Two</div><p class="project-desc">Short description of what this project is about.</p><div class="project-footer"><span class="project-year">2025</span><span class="project-arrow">→</span></div></div>
      </a>
      <a class="project-card reveal" href="#" target="_blank" style="transition-delay:0.2s">
        <div class="project-img-wrap"><img src="project3.jpg" alt="Project 3" onerror="this.style.display='none'; this.parentElement.style.background='linear-gradient(135deg,#001a1a,#003030)'"><div class="project-overlay"><span class="overlay-btn">View Project →</span></div></div>
        <div class="project-info"><div class="project-tags"><span class="project-tag">Graphic Design</span></div><div class="project-name">Project Title Three</div><p class="project-desc">Short description of what this project is about.</p><div class="project-footer"><span class="project-year">2024</span><span class="project-arrow">→</span></div></div>
      </a>
      <a class="project-card reveal" href="#" target="_blank" style="transition-delay:0.1s">
        <div class="project-img-wrap"><img src="project4.jpg" alt="Project 4" onerror="this.style.display='none'; this.parentElement.style.background='linear-gradient(135deg,#1a0a00,#3a1a00)'"><div class="project-overlay"><span class="overlay-btn">View Project →</span></div></div>
        <div class="project-info"><div class="project-tags"><span class="project-tag">Landing Page</span><span class="project-tag">UI/UX</span></div><div class="project-name">Project Title Four</div><p class="project-desc">Short description of what this project is about.</p><div class="project-footer"><span class="project-year">2024</span><span class="project-arrow">→</span></div></div>
      </a>
      <a class="project-card reveal" href="#" target="_blank" style="transition-delay:0.2s">
        <div class="project-img-wrap"><img src="project5.jpg" alt="Project 5" onerror="this.style.display='none'; this.parentElement.style.background='linear-gradient(135deg,#0a001a,#1a003a)'"><div class="project-overlay"><span class="overlay-btn">View Project →</span></div></div>
        <div class="project-info"><div class="project-tags"><span class="project-tag">PHP</span><span class="project-tag">Web App</span></div><div class="project-name">Project Title Five</div><p class="project-desc">Short description of what this project is about.</p><div class="project-footer"><span class="project-year">2024</span><span class="project-arrow">→</span></div></div>
      </a>
      <a class="project-card reveal" href="#" target="_blank" style="transition-delay:0.3s">
        <div class="project-img-wrap"><img src="project6.jpg" alt="Project 6" onerror="this.style.display='none'; this.parentElement.style.background='linear-gradient(135deg,#001a0a,#003a1a)'"><div class="project-overlay"><span class="overlay-btn">View Project →</span></div></div>
        <div class="project-info"><div class="project-tags"><span class="project-tag">Branding</span><span class="project-tag">Print</span></div><div class="project-name">Project Title Six</div><p class="project-desc">Short description of what this project is about.</p><div class="project-footer"><span class="project-year">2024</span><span class="project-arrow">→</span></div></div>
      </a>
    </div>
  </section>

  <!-- ── SERVICES ── -->
  <section id="services">
    <div class="section-divider reveal"><div class="divider-line"></div><p class="section-heading">Services</p><div class="divider-line"></div></div>
    <h2 class="section-title reveal" style="text-align:center; margin-bottom:50px">What I <span>Offer</span></h2>
    <div class="services-grid">
      <div class="glass-card service-card reveal"><span class="service-icon">🎨</span><h3 class="service-name">UI / UX Design</h3><p class="service-desc">Clean, modern interfaces designed for impact. From wireframes to polished high-fidelity mockups that your users will love.</p><span class="service-price">Commission Open</span></div>
      <div class="glass-card service-card reveal" style="transition-delay:0.15s"><span class="service-icon">💻</span><h3 class="service-name">Web Development</h3><p class="service-desc">Responsive, fast, and beautifully coded websites. HTML, CSS, JS, and PHP — built to perform and built to impress.</p><span class="service-price">Commission Open</span></div>
      <div class="glass-card service-card reveal" style="transition-delay:0.3s"><span class="service-icon">✦</span><h3 class="service-name">Branding &amp; Identity</h3><p class="service-desc">Logos, visual systems, and brand guidelines that give your project a distinct and memorable personality.</p><span class="service-price">Commission Open</span></div>
      <div class="glass-card service-card reveal" style="transition-delay:0.1s"><span class="service-icon">🖼️</span><h3 class="service-name">Graphic Design</h3><p class="service-desc">Posters, social media graphics, digital assets — creative work tailored to your brand voice and platform.</p><span class="service-price">Commission Open</span></div>
      <div class="glass-card service-card reveal" style="transition-delay:0.25s"><span class="service-icon">⚙️</span><h3 class="service-name">Custom Projects</h3><p class="service-desc">Have something unique in mind? I'm all ears. Let's scope it out and build exactly what you're imagining.</p><span class="service-price">Let's Talk</span></div>
      <div class="glass-card service-card reveal" style="transition-delay:0.4s"><span class="service-icon">🚀</span><h3 class="service-name">Portfolio / Landing Pages</h3><p class="service-desc">One-page sites that make a lasting first impression — perfect for creatives, freelancers, and small businesses.</p><span class="service-price">Commission Open</span></div>
    </div>
  </section>

  <!-- ── CONTACT ── -->
  <section id="contact">
    <div class="section-divider reveal"><div class="divider-line"></div><p class="section-heading">Contact</p><div class="divider-line"></div></div>
    <h2 class="section-title reveal" style="text-align:center; margin-bottom:10px">Let's <span>Work Together</span></h2>
    <p class="reveal" style="text-align:center; color:var(--muted); font-size:13px; margin-bottom:40px; letter-spacing:1px">Open for commissions — drop a message and I'll get back to you within 24 hrs.</p>

    <div class="glass-card contact-card reveal">

      <?php if ($form_success): ?>
        <!-- ✅ SUCCESS MESSAGE -->
        <div class="alert alert-success">
          ✓ &nbsp; Message sent! I'll get back to you within 24 hours.
        </div>
      <?php elseif ($form_error !== ''): ?>
        <!-- ❌ ERROR MESSAGE -->
        <div class="alert alert-error">
          ✕ &nbsp; <?= htmlspecialchars($form_error) ?>
        </div>
      <?php endif; ?>

      <!-- CONTACT FORM — posts to same page (index.php#contact) -->
      <form class="contact-form" method="POST" action="#contact">
        <div class="form-row">
          <div class="form-group">
            <label>Your Name</label>
            <input type="text" name="name" placeholder="NAME"
                   value="<?= $form_success ? '' : htmlspecialchars($_POST['name'] ?? '') ?>" required />
          </div>
          <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="john@example.com"
                   value="<?= $form_success ? '' : htmlspecialchars($_POST['email'] ?? '') ?>" required />
          </div>
        </div>
        <div class="form-group">
          <label>Service Interested In</label>
          <select name="service">
            <option <?= (!$form_success && (($_POST['service'] ?? '') === 'UI / UX Design'))          ? 'selected' : '' ?>>UI / UX Design</option>
            <option <?= (!$form_success && (($_POST['service'] ?? '') === 'Web Development'))          ? 'selected' : '' ?>>Web Development</option>
            <option <?= (!$form_success && (($_POST['service'] ?? '') === 'Branding & Identity'))      ? 'selected' : '' ?>>Branding &amp; Identity</option>
            <option <?= (!$form_success && (($_POST['service'] ?? '') === 'Graphic Design'))           ? 'selected' : '' ?>>Graphic Design</option>
            <option <?= (!$form_success && (($_POST['service'] ?? '') === 'Portfolio / Landing Page')) ? 'selected' : '' ?>>Portfolio / Landing Page</option>
            <option <?= (!$form_success && (($_POST['service'] ?? '') === 'Custom Project'))           ? 'selected' : '' ?>>Custom Project</option>
          </select>
        </div>
        <div class="form-group">
          <label>Message</label>
          <textarea name="message" rows="5" placeholder="Tell me about your project..." required><?= $form_success ? '' : htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        </div>
        <!-- Hidden field so PHP knows this is the contact form -->
        <input type="hidden" name="contact_submit" value="1" />
        <button type="submit" class="btn-submit">Send Message →</button>
      </form>

      <div class="contact-links">
        <a class="glass-card contact-link email" href="mailto:your@email.com">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#00f5ff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><polyline points="2,4 12,14 22,4"/></svg>
          <span>Email Me</span>
        </a>
        <a class="glass-card contact-link instagram" href="#" target="_blank">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <defs><linearGradient id="igGrad" x1="0" y1="24" x2="24" y2="0"><stop offset="0%" stop-color="#f09433"/><stop offset="25%" stop-color="#e6683c"/><stop offset="50%" stop-color="#dc2743"/><stop offset="75%" stop-color="#cc2366"/><stop offset="100%" stop-color="#bc1888"/></linearGradient></defs>
            <rect x="2" y="2" width="20" height="20" rx="6" stroke="url(#igGrad)" stroke-width="1.8"/><circle cx="12" cy="12" r="4.5" stroke="url(#igGrad)" stroke-width="1.8"/><circle cx="17.5" cy="6.5" r="1.3" fill="url(#igGrad)"/>
          </svg>
          <span>Instagram</span>
        </a>
        <a class="glass-card contact-link facebook" href="#" target="_blank">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <defs><linearGradient id="fbGrad" x1="0" y1="0" x2="0" y2="24"><stop offset="0%" stop-color="#42a5f5"/><stop offset="100%" stop-color="#1565c0"/></linearGradient></defs>
            <rect x="2" y="2" width="20" height="20" rx="5" fill="url(#fbGrad)"/><path d="M13.5 8H15.5V5.5H13.5C12.1739 5.5 11 6.67 11 8V9.5H9V12H11V19H13.5V12H15.5L16 9.5H13.5V8Z" fill="white"/>
          </svg>
          <span>Facebook</span>
        </a>
        <a class="glass-card contact-link github" href="#" target="_blank">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <defs><linearGradient id="ghGrad" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stop-color="#a78bfa"/><stop offset="100%" stop-color="#6d28d9"/></linearGradient></defs>
            <circle cx="12" cy="12" r="10" fill="url(#ghGrad)"/><path d="M12 4C7.58 4 4 7.58 4 12c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0020 12c0-4.42-3.58-8-8-8z" fill="white"/>
          </svg>
          <span>GitHub</span>
        </a>
        <a class="glass-card contact-link twitter" href="#" target="_blank">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <defs><linearGradient id="xGrad" x1="0" y1="0" x2="24" y2="24"><stop offset="0%" stop-color="#38bdf8"/><stop offset="100%" stop-color="#0284c7"/></linearGradient></defs>
            <circle cx="12" cy="12" r="10" fill="url(#xGrad)"/><path d="M14.1 7H16.5L13.2 10.8L17 17H14L11.8 13.8L9.2 17H6.8L10.3 13L6.5 7H9.6L11.6 9.9L14.1 7ZM13.3 15.5H14.4L9.7 8.5H8.5L13.3 15.5Z" fill="white"/>
          </svg>
          <span>Twitter / X</span>
        </a>
      </div>
    </div>
  </section>

  <footer>
    <p>© 2025 <span>Laurence Ivan Namoc</span> — All rights reserved</p>
    <p style="margin-top:8px; font-size:10px;">Designed &amp; Built by <span>LIN</span></p>
  </footer>

  <script>
    const reveals = document.querySelectorAll('.reveal');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => { if (entry.isIntersecting) entry.target.classList.add('visible'); });
    }, { threshold: 0.10 });
    reveals.forEach(el => observer.observe(el));

    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => {
        e.preventDefault();
        const target = document.querySelector(a.getAttribute('href'));
        if (target) target.scrollIntoView({ behavior: 'smooth' });
      });
    });

    // Auto-scroll to contact section on page load if form was submitted
    <?php if ($form_success || $form_error !== ''): ?>
    window.addEventListener('load', () => {
      document.querySelector('#contact').scrollIntoView({ behavior: 'smooth' });
    });
    <?php endif; ?>

    document.addEventListener('mousemove', e => {
      const x = (e.clientX / window.innerWidth - 0.5) * 30;
      const y = (e.clientY / window.innerHeight - 0.5) * 30;
      document.querySelector('.orb-1').style.transform = `translate(${x * 0.5}px, ${y * 0.5}px)`;
      document.querySelector('.orb-2').style.transform = `translate(${-x * 0.4}px, ${-y * 0.4}px)`;
      document.querySelector('.orb-3').style.transform = `translate(${x * 0.3}px, ${y * 0.6}px)`;
    });

    const profileCard = document.querySelector('.profile-card');
    if (profileCard) {
      profileCard.addEventListener('mousemove', e => {
        const rect = profileCard.getBoundingClientRect();
        const cx = rect.left + rect.width / 2;
        const cy = rect.top + rect.height / 2;
        const dx = (e.clientX - cx) / (rect.width / 2);
        const dy = (e.clientY - cy) / (rect.height / 2);
        profileCard.style.transform = `translateY(-8px) rotateX(${-dy * 8}deg) rotateY(${dx * 8}deg) scale(1.02)`;
      });
      profileCard.addEventListener('mouseleave', () => {
        profileCard.style.transform = '';
        profileCard.style.transition = 'transform 0.6s cubic-bezier(0.22,1,0.36,1), box-shadow 0.4s ease, border-color 0.4s ease';
      });
      profileCard.addEventListener('mouseenter', () => {
        profileCard.style.transition = 'transform 0.1s linear, box-shadow 0.4s ease, border-color 0.4s ease';
      });
    }
  </script>
</body>
</html>