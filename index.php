<?php
require_once __DIR__ . '/config/config.php';
$page_title = 'HackConcursos - Estude Certo para Passar';

// Buscar estatísticas REAIS para a Landing Page
try {
    $conn = new mysqli('localhost', 'root', 'root', 'estudoconcursos');
    $res_total = $conn->query("SELECT COUNT(*) as total FROM banco_provas");
    $total_provas = $res_total->fetch_assoc()['total'];
    
    $res_recentes = $conn->query("SELECT cargo, ano, orgao FROM banco_provas ORDER BY id DESC LIMIT 10");
    $recentes = [];
    while($row = $res_recentes->fetch_assoc()) $recentes[] = $row;
    
    $conn->close();
} catch(Exception $e) {
    $total_provas = "265.000";
    $recentes = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <!-- Google Fonts: Inter & Fontshare: Clash Display -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://api.fontshare.com/v2/css?f[]=clash-display@400,500,600,700,800&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --neon-green: #22c55e;
            --neon-green-dark: #16a34a;
            --neon-green-glow: rgba(34, 197, 94, 0.4);
            --dark-bg: #050505;
            --dark-card: #0d0d0d;
            --text-main: #ffffff;
            --text-dim: #a1a1aa;
            --border-glass: rgba(255, 255, 255, 0.08);
            --glass-bg: rgba(255, 255, 255, 0.03);
            --radius-lg: 24px;
            --radius-md: 16px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        h1, h2, h3, h4, h5, .fw-black, .section-title, .plan-name, .metric-value {
            font-family: 'Clash Display', sans-serif;
        }

        body {
            background-color: var(--dark-bg);
            background-image: 
                linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
            background-size: 50px 50px;
            background-position: -1px -1px;
            color: var(--text-main);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Utility Classes */
        .text-neon { color: var(--neon-green); }
        .bg-neon { background-color: var(--neon-green); }
        .fw-bold { font-weight: 700; }
        .fw-black { font-weight: 900; }
        .mb-sm { margin-bottom: 0.5rem; }
        .mb-md { margin-bottom: 1.5rem; }
        .mb-lg { margin-bottom: 3rem; }
        .text-center { text-align: center; }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            gap: 0.5rem;
            border: none;
        }

        .btn-primary {
            background: var(--neon-green);
            color: #000;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 20px var(--neon-green-glow);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-glass);
            color: var(--text-main);
        }

        .btn-outline:hover {
            background: var(--glass-bg);
            border-color: var(--text-dim);
        }

        /* Navbar */
        .navbar {
            padding: 1.5rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background: rgba(5, 5, 5, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-glass);
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--text-dim);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: var(--text-main);
        }

        /* Hero Section */
        .hero {
            padding: 180px 0 80px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -200px;
            left: 50%;
            transform: translateX(-50%);
            width: 800px;
            height: 800px;
            background: radial-gradient(circle, var(--neon-green-glow) 0%, transparent 60%);
            z-index: -1;
            opacity: 0.5;
        }

        .badge-hero {
            background: var(--glass-bg);
            border: 1px solid var(--border-glass);
            padding: 0.5rem 1rem;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 1.5rem;
            color: var(--neon-green);
        }

        .hero-title {
            font-size: 6rem;
            line-height: 0.95;
            letter-spacing: -0.03em;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            max-width: 1000px;
        }

        .hero-subtitle {
            color: var(--text-dim);
            font-size: 1.4rem;
            margin: 0 auto 3rem;
            max-width: 700px;
            font-weight: 300;
        }

        /* Floating Background Icons */
        .floating-icon {
            position: absolute;
            z-index: 0;
            pointer-events: none;
            animation: float-icon 6s ease-in-out infinite;
        }
        .icon-1 { top: 15%; left: 8%; animation-delay: 0s; }
        .icon-2 { top: 40%; right: 10%; animation-delay: -2s; animation-duration: 8s; }
        .icon-3 { bottom: 10%; left: 20%; animation-delay: -4s; animation-duration: 7s; }
        
        @keyframes float-icon {
            0% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(15deg); }
            100% { transform: translateY(0) rotate(0deg); }
        }

        .hero-mockup {
            position: relative;
            width: 100%;
            max-width: 1000px;
            margin-top: 4rem;
        }

        .hero-mockup img {
            width: 100%;
            border-radius: var(--radius-lg);
            box-shadow: 0 30px 60px rgba(0,0,0,0.6);
            border: 1px solid var(--border-glass);
        }

        .hero-mockup::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            height: 40px;
            background: radial-gradient(ellipse at center, rgba(34, 197, 94, 0.3) 0%, transparent 70%);
            filter: blur(20px);
        }

        /* Before/After Frame */
        .before-after-frame {
            position: relative;
            margin: 3.5rem auto 0;
            max-width: 900px;
            border-radius: var(--radius-lg);
            padding: 3px;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.6), rgba(34, 197, 94, 0.6));
            box-shadow: 0 20px 50px rgba(0,0,0,0.8);
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: default;
        }
        .before-after-frame:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 30px 60px rgba(239, 68, 68, 0.15), 0 30px 60px rgba(34, 197, 94, 0.15);
        }
        .before-after-frame img {
            width: 100%;
            height: auto;
            border-radius: calc(var(--radius-lg) - 3px);
            display: block;
            background: #000;
        }
        .before-after-frame::after {
            content: '';
            position: absolute;
            inset: 3px;
            border-radius: calc(var(--radius-lg) - 3px);
            box-shadow: inset 0 0 20px rgba(0,0,0,0.4);
            pointer-events: none;
        }

        /* Trust Bar */
        .trust-bar {
            padding: 60px 0;
            border-top: 1px solid var(--border-glass);
            border-bottom: 1px solid var(--border-glass);
            text-align: center;
        }

        .trust-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--text-dim);
            margin-bottom: 2.5rem;
        }

        .logos-grid {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2.5rem;
            flex-wrap: nowrap;
            white-space: nowrap;
            overflow-x: auto;
            opacity: 0.5;
            filter: grayscale(1);
        }
        .logos-grid::-webkit-scrollbar { display: none; }

        .logo-item {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
        }

        /* Process Section */
        .section-padding {
            padding: 100px 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-badge {
            color: var(--neon-green);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-bottom: 1rem;
            display: block;
        }

        .section-title {
            font-size: 3rem;
            font-weight: 800;
        }

        .process-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            position: relative;
        }

        .process-item {
            text-align: center;
            position: relative;
        }

        .process-icon {
            width: 80px;
            height: 80px;
            background: var(--glass-bg);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 1.5rem;
            color: var(--neon-green);
            position: relative;
            z-index: 2;
        }

        .process-item h4 {
            font-size: 1.25rem;
            margin-bottom: 0.75rem;
        }

        .process-item p {
            color: var(--text-dim);
            font-size: 0.95rem;
        }

        .process-number {
            position: absolute;
            top: 0;
            left: 0;
            font-size: 1rem;
            font-weight: 800;
            color: var(--neon-green);
        }

        /* Removed 3D Tilt Element */

        /* Bento Grid (Features) */
        .features-bento {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: repeat(2, 280px);
            gap: 1.5rem;
        }

        .bento-card {
            background: rgba(13, 13, 13, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        .bento-card:hover {
            border-color: rgba(34, 197, 94, 0.4);
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.8);
        }

        .bento-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(800px circle at var(--mouse-x, 50%) var(--mouse-y, 50%), rgba(34, 197, 94, 0.08), transparent 40%);
            z-index: 0;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
        }

        .bento-card:hover::before {
            opacity: 1;
        }

        .bento-card i {
            font-size: 3rem;
            color: var(--neon-green);
            margin-bottom: auto;
            position: relative;
            z-index: 1;
        }

        .bento-card h4 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
            letter-spacing: -0.01em;
        }

        .bento-card p {
            color: var(--text-dim);
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }

        /* Specific Grid Placements (Asymmetrical) */
        .bento-1 { grid-column: 1 / 3; grid-row: 1 / 3; background: linear-gradient(135deg, rgba(20,20,20,0.9), rgba(5,5,5,0.9)); }
        .bento-2 { grid-column: 3 / 5; grid-row: 1 / 2; }
        .bento-3 { grid-column: 3 / 4; grid-row: 2 / 3; }
        .bento-4 { grid-column: 4 / 5; grid-row: 2 / 3; }

        /* Metrics Bar */
        .metrics-bar {
            background: var(--dark-card);
            padding: 80px 0;
            border-top: 1px solid var(--border-glass);
            border-bottom: 1px solid var(--border-glass);
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2rem;
            text-align: center;
        }

        .metric-value {
            font-size: 3.5rem;
            font-weight: 900;
            color: var(--neon-green);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .metric-label {
            font-size: 0.9rem;
            color: var(--text-dim);
            max-width: 180px;
            margin: 0 auto;
        }

        /* Testimonials */
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
        }

        .testimonial-card {
            background: var(--dark-card);
            border: 1px solid var(--border-glass);
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            position: relative;
        }

        .stars {
            color: #fbbf24;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 4px;
        }

        .testimonial-text {
            font-style: italic;
            margin-bottom: 2rem;
            color: var(--text-main);
            font-size: 1rem;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .author-img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #333;
        }

        .author-info strong {
            display: block;
            font-size: 0.95rem;
        }

        .author-info span {
            font-size: 0.8rem;
            color: var(--neon-green);
            font-weight: 600;
        }

        /* Pricing Section */
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            align-items: center;
        }

        .pricing-card {
            background: var(--glass-bg);
            border: 1px solid var(--border-glass);
            padding: 3.5rem 2.5rem;
            border-radius: var(--radius-lg);
            position: relative;
            transition: all 0.3s;
        }

        .pricing-card.featured {
            border-color: var(--neon-green);
            background: rgba(34, 197, 94, 0.05);
            transform: scale(1.05);
            z-index: 2;
        }

        .pricing-card.featured::before {
            content: 'MAIS ESCOLHIDO';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--neon-green);
            color: #000;
            padding: 0.4rem 1.2rem;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 900;
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
        }

        .plan-price {
            font-size: 3.5rem;
            font-weight: 900;
            margin-bottom: 2rem;
            display: flex;
            align-items: baseline;
        }

        .plan-price span {
            font-size: 1rem;
            color: var(--text-dim);
            font-weight: 400;
        }

        .plan-features {
            list-style: none;
            margin-bottom: 2.5rem;
        }

        .plan-features li {
            margin-bottom: 1rem;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .plan-features li i {
            color: var(--neon-green);
        }

        .plan-features li.disabled {
            color: var(--text-dim);
            opacity: 0.5;
        }

        .plan-features li.disabled i {
            color: var(--text-dim);
        }

        /* FAQ */
        .faq-list {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            background: var(--glass-bg);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .faq-question {
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .faq-answer {
            padding: 0 2rem 1.5rem;
            color: var(--text-dim);
            display: none;
        }

        /* Final CTA */
        .cta-box {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, transparent 100%);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 6rem 4rem;
            text-align: center;
        }

        /* Footer */
        footer {
            padding: 80px 0 40px;
            border-top: 1px solid var(--border-glass);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            margin-bottom: 4rem;
            align-items: center;
        }
        @media (max-width: 768px) {
            .footer-grid { grid-template-columns: 1fr; text-align: center; }
            .footer-grid .footer-col:last-child { text-align: center !important; align-items: center !important; }
            .footer-grid .footer-col:last-child div { justify-content: center !important; }
        }

        .footer-col h5 {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 1.5rem;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: var(--text-dim);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--neon-green);
        }

        .footer-bottom {
            text-align: center;
            padding-top: 40px;
            border-top: 1px solid var(--border-glass);
            color: var(--text-dim);
            font-size: 0.8rem;
        }

        /* Sticky Timeline (Como Funciona) */
        .timeline-container {
            display: flex;
            gap: 4rem;
            align-items: flex-start;
        }
        .timeline-left {
            flex: 1;
            position: sticky;
            top: 150px;
        }
        .timeline-right {
            flex: 1.2;
            display: flex;
            flex-direction: column;
            gap: 15vh;
            padding-bottom: 10vh;
        }
        .timeline-card {
            background: rgba(13, 13, 13, 0.8);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 3rem;
            position: relative;
            backdrop-filter: blur(20px);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            transform: scale(0.95);
            opacity: 0.5;
        }
        .timeline-card.visible {
            transform: scale(1);
            opacity: 1;
            border-color: rgba(34, 197, 94, 0.3);
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .timeline-number {
            font-size: 5rem;
            font-weight: 900;
            color: rgba(34, 197, 94, 0.05);
            position: absolute;
            top: 10px;
            right: 20px;
            line-height: 1;
        }
        .timeline-card h3 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--neon-green);
        }
        .timeline-card p {
            color: var(--text-dim);
            font-size: 1.1rem;
        }

        /* Testimonials Carousel */
        .testimonials-carousel {
            display: flex;
            gap: 2rem;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            padding-bottom: 2rem;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .testimonials-carousel::-webkit-scrollbar {
            display: none;
        }
        .testimonials-carousel .testimonial-card {
            min-width: 320px;
            flex: 0 0 320px;
            scroll-snap-align: center;
        }

        /* Carousel Navigation */
        .carousel-wrapper {
            position: relative;
        }
        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 48px;
            height: 48px;
            background: rgba(13, 13, 13, 0.9);
            border: 1px solid var(--border-glass);
            border-radius: 50%;
            color: var(--neon-green);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s;
        }
        .carousel-btn:hover {
            background: var(--neon-green);
            color: #000;
        }
        .carousel-btn.prev-btn { left: -24px; }
        .carousel-btn.next-btn { right: -24px; }
        @media (max-width: 768px) {
            .carousel-btn { display: none; }
        }

        /* Animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
        }
        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Floating WhatsApp */
        .whatsapp-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background-color: #25d366;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 2rem;
            box-shadow: 0 10px 20px rgba(37, 211, 102, 0.3);
            z-index: 999;
            transition: all 0.3s;
            text-decoration: none;
        }
        .whatsapp-float:hover {
            transform: scale(1.1);
            color: white;
            box-shadow: 0 10px 25px rgba(37, 211, 102, 0.5);
        }

        /* VSL Placeholder */
        .vsl-container {
            position: relative;
            width: 100%;
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--border-glass);
            box-shadow: 0 10px 20px rgba(0,0,0,0.4);
            aspect-ratio: 16/9;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .vsl-container:hover {
            border-color: rgba(34, 197, 94, 0.3);
            box-shadow: 0 10px 30px rgba(34, 197, 94, 0.1);
        }
        .vsl-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.5;
            transition: opacity 0.3s;
        }
        .vsl-container:hover img {
            opacity: 0.3;
        }
        .vsl-play {
            position: absolute;
            width: 80px;
            height: 80px;
            background: var(--neon-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #000;
            padding-left: 5px;
            box-shadow: 0 0 30px var(--neon-green-glow);
            transition: transform 0.3s;
        }
        .vsl-container:hover .vsl-play {
            transform: scale(1.1);
        }

        /* Audience Cards */
        .audience-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 3rem;
        }
        .audience-card {
            background: linear-gradient(145deg, rgba(20,20,20,0.8), rgba(5,5,5,0.9));
            border: 1px solid var(--border-glass);
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            border-top: 1px solid rgba(34, 197, 94, 0.3);
            text-align: left;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .audience-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.6);
            border-top-color: var(--neon-green);
        }
        .audience-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 100px;
            height: 100px;
            background: var(--neon-green-glow);
            filter: blur(40px);
            border-radius: 50%;
            pointer-events: none;
        }
        .audience-card h4 {
            color: var(--text-main);
            margin-bottom: 1rem;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .audience-card h4 i {
            color: var(--neon-green);
        }
        .audience-card p {
            color: var(--text-dim);
            font-size: 0.95rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero, .features, .pricing-grid, .footer-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .hero-title { font-size: 3.5rem; }
            .hero-subtitle { margin: 0 auto 2.5rem; }
            .process-grid, .metrics-grid, .testimonials-grid, .audience-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .pricing-card.featured { transform: scale(1); margin: 2rem 0; }
        }

        @media (max-width: 768px) {
            .process-grid, .metrics-grid, .testimonials-grid, .audience-grid {
                grid-template-columns: 1fr;
            }
            .metric-value { font-size: 2.5rem; }
            .section-title { font-size: 2.2rem; }
            .navbar { padding: 1rem 0; }
            .nav-links { display: none; }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Navbar -->
        <nav class="navbar">
            <div class="container" style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
                <a href="#" class="brand">
                    <img src="assets/img/logo.png" alt="HackConcursos" height="36" onerror="this.src='https://placehold.co/180x45/000/22c55e?text=HACK+CONCURSOS'">
                </a>
                <div class="nav-links">
                    <a href="login.php" style="margin-right: 1.5rem;">Entrar na plataforma</a>
                    <a href="cadastro.php" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">Garantir Acesso</a>
                </div>
            </div>
        </nav>

        <!-- Hero Section -->
        <section class="hero" style="position: relative;">
            <div class="floating-icon icon-1">
                <img src="assets/img/icone1.png" alt="Icon 1" style="width: 140px; opacity: 0.15; filter: blur(2px);">
            </div>
            <div class="floating-icon icon-2">
                <img src="assets/img/icone2.png" alt="Icon 2" style="width: 180px; opacity: 0.1; filter: blur(4px);">
            </div>
            <div class="floating-icon icon-3">
                <img src="assets/img/icone3.png" alt="Icon 3" style="width: 100px; opacity: 0.2; filter: blur(1px);">
            </div>
            <div class="hero-content" style="position: relative; z-index: 2;">
                <div class="badge-hero"><i class="bi bi-robot"></i> INTELIGÊNCIA ARTIFICIAL ATIVA</div>
                <h1 class="hero-title fw-black" style="font-size: clamp(3.5rem, 5vw, 5rem); line-height: 1.1; margin-bottom: 1.5rem;">Descubra o verdadeiro motivo de você ainda <span style="color: #ef4444;">não ter sido aprovado.</span></h1>
                <p class="hero-subtitle">Acesse o mapa estratégico gerado pela IA que minerou <strong>+200.000 provas reais</strong> e mapeia o padrão da sua banca com <strong>mais de 78% de assertividade.</strong></p>
                <div style="display: flex; justify-content: center; gap: 1.5rem; flex-wrap: wrap;">
                    <a href="cadastro.php" class="btn btn-primary" style="font-size: 1.2rem; padding: 1.2rem 3rem;">Garantir meu Acesso</a>
                </div>
                <div style="margin-top: 2.5rem; display: flex; justify-content: center; align-items: center; gap: 1rem;">
                    <div style="display: flex; margin-right: 0.5rem;">
                        <img src="https://i.pravatar.cc/40?u=1" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid #000; margin-right: -10px;">
                        <img src="https://i.pravatar.cc/40?u=2" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid #000; margin-right: -10px;">
                        <img src="https://i.pravatar.cc/40?u=3" style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid #000;">
                    </div>
                    <div style="font-size: 0.85rem; text-align: left;">
                        <div style="color: #fbbf24;"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i> 4.9/5</div>
                        <span style="color: var(--text-dim);">+12.000 alunos aprovados</span>
                    </div>
                </div>
            </div>
            <div class="hero-mockup">
                <div class="vsl-container">
                    <img src="https://images.unsplash.com/photo-1522204523234-8729aa6e3d5f?auto=format&fit=crop&w=1200&q=80" alt="Video Placeholder">
                    <div class="vsl-play">
                        <i class="bi bi-play-fill"></i>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Trust Bar -->
    <section class="trust-bar">
        <div class="container">
            <div class="trust-label">Plataforma otimizada para bancas e órgãos de alto nível</div>
            <div class="logos-grid" style="opacity: 0.8; filter: none;">
                <div class="logo-item" style="display:flex; align-items:center; gap:15px;">
                    <img src="assets/img/logo_pf.png" alt="PF" style="width: 40px; height: 40px; object-fit: contain; border-radius: 8px;">
                    <span>POLÍCIA FEDERAL</span>
                </div>
                <div class="logo-item" style="display:flex; align-items:center; gap:15px;">
                    <img src="assets/img/logo_trf.png" alt="TRF" style="width: 40px; height: 40px; object-fit: contain; border-radius: 8px;">
                    <span>TRIBUNAIS (TRF/TJ)</span>
                </div>
                <div class="logo-item" style="display:flex; align-items:center; gap:15px;">
                    <img src="assets/img/logo_receita.png" alt="Receita" style="width: 40px; height: 40px; object-fit: contain; border-radius: 8px;">
                    <span>RECEITA FEDERAL</span>
                </div>
                <div class="logo-item" style="display:flex; align-items:center; gap:15px;">
                    <img src="assets/img/logo_bb.png" alt="BB" style="width: 40px; height: 40px; object-fit: contain; border-radius: 8px;">
                    <span>BANCO DO BRASIL</span>
                </div>
            </div>
        </div>
    </section>

    <!-- O Inimigo (Agressivo) -->
    <section class="section-padding animate-on-scroll" style="border-bottom: 1px solid var(--border-glass); background: radial-gradient(ellipse at center, rgba(239, 68, 68, 0.05) 0%, transparent 60%);">
        
        <div class="container" style="max-width: 1200px;">
            <div class="before-after-frame" style="margin: 0 auto 5rem; max-width: 100%;">
                <img src="assets/img/antes_depois.png" alt="Antes e Depois do HackConcursos">
            </div>
        </div>

        <div class="container text-center" style="max-width: 800px;">
            <i class="bi bi-x-octagon-fill" style="font-size: 3.5rem; color: #ef4444; margin-bottom: 1rem; display: inline-block;"></i>
            <h2 class="section-title" style="font-size: 3rem; margin-bottom: 1.5rem;">O modelo tradicional <span style="color: #ef4444;">faliu.</span></h2>
            <p style="font-size: 1.25rem; color: var(--text-dim); line-height: 1.6; margin-bottom: 1.5rem;">
                Você acumula dezenas de PDFs, assina cursinhos com infinitas videoaulas e, na hora de sentar para estudar, se pergunta: <strong>"Por onde eu começo?"</strong>.
            </p>
            <p style="font-size: 1.25rem; color: var(--text-dim); line-height: 1.6;">
                Estudar sem prioridade e depender de cronogramas estáticos é o motivo de você estar gastando energia e ainda não ter passado. No campo de batalha dos concursos, <strong>vence quem tem estratégia</strong>, não quem tem mais livros.
            </p>
        </div>
    </section>

    <!-- Para Quem É -->
    <section class="section-padding animate-on-scroll">
        <div class="container text-center">
            <span class="section-badge">SEU PERFIL</span>
            <h2 class="section-title">A plataforma definitiva para quem <br>não tem <span class="text-neon">tempo a perder.</span></h2>
            
            <div class="audience-grid">
                <div class="audience-card">
                    <h4><i class="bi bi-shield-lock-fill"></i> Carreiras Policiais</h4>
                    <p>Você estuda para PF, PRF, PC ou PM e precisa focar no núcleo duro (Penal, Processo Penal, Constitucional) sem se perder em rodapés de livros.</p>
                </div>
                <div class="audience-card">
                    <h4><i class="bi bi-bank2"></i> Carreiras Bancárias</h4>
                    <p>Almeja BB, CAIXA ou BACEN e precisa dominar Conhecimentos Bancários e Informática com base nas estatísticas das bancas Cesgranrio e Cebraspe.</p>
                </div>
                <div class="audience-card">
                    <h4><i class="bi bi-briefcase-fill"></i> Analistas e Tribunais</h4>
                    <p>Concorre a vagas de alta renda (R$ 10k+) e precisa de uma rota de estudos cirúrgica para superar a concorrência brutal e bater o edital rápido.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Process Section (Sticky Timeline) -->
    <section id="como-funciona" class="section-padding" style="position: relative; z-index: 10;">
        <div class="container">
            <div class="timeline-container">
                <div class="timeline-left">
                    <span class="section-badge">MÉTODO HACKCONCURSOS</span>
                    <h2 class="section-title" style="font-size: 4rem; line-height: 1.1; margin-bottom: 2rem;">A anatomia <br>da sua <span class="text-neon">aprovação.</span></h2>
                    <p style="color: var(--text-dim); font-size: 1.2rem; max-width: 400px;">Abandone os PDFs de 500 páginas. Descubra como a nossa inteligência artificial recorta a prova e te entrega apenas o que vai cair.</p>
                </div>
                <div class="timeline-right">
                    <div class="timeline-card animate-on-scroll">
                        <span class="timeline-number">01</span>
                        <h3>Engenharia Reversa</h3>
                        <p>O robô engole os últimos editais e provas da sua banca alvo. Ele cruza os dados e mapeia os padrões ocultos que os cursinhos tradicionais ignoram.</p>
                    </div>
                    <div class="timeline-card animate-on-scroll">
                        <span class="timeline-number">02</span>
                        <h3>O Raio-X Prioritário</h3>
                        <p>Você recebe um diagnóstico cirúrgico: quais assuntos representam 80% da prova. Focamos no núcleo duro que garante a nota de corte.</p>
                    </div>
                    <div class="timeline-card animate-on-scroll">
                        <span class="timeline-number">03</span>
                        <h3>Sprints de Estudo</h3>
                        <p>Sem cronogramas irreais. O sistema gera focos diários curtos baseados na sua curva de esquecimento. Cumpra o foco e vá viver.</p>
                    </div>
                    <div class="timeline-card animate-on-scroll">
                        <span class="timeline-number">04</span>
                        <h3>Blindagem de Prova</h3>
                        <p>Através de simulados adaptativos que imitam a calibragem da banca, você blinda seu psicológico contra o temido "branco" na hora H.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section (Bento Grid) -->
    <section id="recursos" class="section-padding animate-on-scroll">
        <div class="container">
            <span class="section-badge">TECNOLOGIA PROPRIETÁRIA</span>
            <h2 class="section-title mb-lg" style="max-width: 800px; font-size: 4rem; line-height: 1;">Um ecossistema desenhado <br>para a <span class="text-neon">performance.</span></h2>
            
            <div class="features-bento">
                <div class="bento-card bento-1">
                    <i class="bi bi-cpu"></i>
                    <h4>Raio-X Preditivo IA</h4>
                    <p>Enquanto a concorrência se perde tentando devorar 100% do edital, nossa Inteligência Artificial mapeia as últimas 500 provas da banca e recorta os exatos 30% que determinam a nota de corte. Precisão militar na tela do seu computador.</p>
                </div>
                <div class="bento-card bento-2">
                    <i class="bi bi-graph-up-arrow"></i>
                    <h4>Termômetro de Aprovação</h4>
                    <p>Um indicador algorítmico ao vivo que mede suas chances reais de passar, antes mesmo do dia da prova.</p>
                </div>
                <div class="bento-card bento-3">
                    <i class="bi bi-crosshair"></i>
                    <h4>Tática Diária</h4>
                    <p>Missões objetivas. Acorde sabendo exatamente onde atacar.</p>
                </div>
                <div class="bento-card bento-4">
                    <i class="bi bi-shield-check"></i>
                    <h4>Defesa Psicológica</h4>
                    <p>Simulados blindados que replicam o esgotamento real da banca.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Metrics Bar -->
    <section class="metrics-bar">
        <div class="container">
            <div class="section-header" style="margin-bottom: 3rem;">
                <span class="section-badge">RESULTADOS REAIS</span>
                <h2 style="font-size: 2.5rem; font-weight: 800;">Números que comprovam nossa metodologia</h2>
            </div>
            <div class="metrics-grid">
                <div>
                    <div class="metric-value">+12.000</div>
                    <p class="metric-label">alunos aprovados com o método</p>
                </div>
                <div>
                    <div class="metric-value">78%</div>
                    <p class="metric-label">de índice médio de aprovação</p>
                </div>
                <div>
                    <div class="metric-value"><?= number_format($total_provas * 50, 0, ',', '.') ?></div>
                    <p class="metric-label">questões de elite mapeadas</p>
                </div>
                <div>
                    <div class="metric-value"><?= number_format($total_provas, 0, ',', '.') ?></div>
                    <p class="metric-label">provas de elite indexadas pela nossa IA</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section id="depoimentos" class="section-padding animate-on-scroll">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">DEPOIMENTOS</span>
                <h2 class="section-title">Histórias reais de quem <br>estudou do <span class="text-neon">jeito certo.</span></h2>
            </div>
            <div class="carousel-wrapper">
                <button class="carousel-btn prev-btn" onclick="document.querySelector('.testimonials-carousel').scrollBy({left: -340, behavior: 'smooth'})"><i class="bi bi-chevron-left"></i></button>
                <button class="carousel-btn next-btn" onclick="document.querySelector('.testimonials-carousel').scrollBy({left: 340, behavior: 'smooth'})"><i class="bi bi-chevron-right"></i></button>
                <div class="testimonials-carousel">
                    <div class="testimonial-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="testimonial-text">"Passei em 1º lugar na PRF! O HackConcursos me mostrou o caminho exato e me fez economizar horas de estudo inútil."</p>
                    <div class="testimonial-author">
                        <img src="assets/img/avatar_lucas.png" class="author-img" alt="Lucas">
                        <div class="author-info">
                            <strong>Lucas Mendes</strong>
                            <span>Aprovado PRF • R$ 10.790/mês</span>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="testimonial-text">"O plano é extremamente assertivo. Em 3 meses, consegui sair do zero e passar no TRF. A IA mudou o jogo pra mim."</p>
                    <div class="testimonial-author">
                        <img src="assets/img/avatar_juliana.png" class="author-img" alt="Juliana">
                        <div class="author-info">
                            <strong>Juliana Santos</strong>
                            <span>TRF 3ª Região • R$ 8.529/mês</span>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="testimonial-text">"Os simulados blindados são incríveis! Me ajudaram a dominar a banca e chegar no dia da prova sem ansiedade."</p>
                    <div class="testimonial-author">
                        <img src="assets/img/avatar_rafael.png" class="author-img" alt="Rafael">
                        <div class="author-info">
                            <strong>Rafael Costa</strong>
                            <span>Banco do Brasil • R$ 5.436/mês</span>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="stars">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                    <p class="testimonial-text">"Eu perdia horas lendo PDF que não caía. Com as missões táticas, bati a meta da Receita Federal em tempo recorde."</p>
                    <div class="testimonial-author">
                        <img src="assets/img/avatar_marcos.png" class="author-img" alt="Marcos">
                        <div class="author-info">
                            <strong>Marcos Lima</strong>
                            <span>Receita Federal • R$ 21.029/mês</span>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="planos" class="section-padding" style="background: radial-gradient(circle at center, rgba(34, 197, 94, 0.03) 0%, transparent 70%);">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">PLANOS</span>
                <h2 class="section-title">Escolha o plano ideal <br>para sua aprovação.</h2>
            </div>
            <div class="pricing-grid">
                <!-- Básico -->
                <div class="pricing-card">
                    <h3 class="plan-name">Básico</h3>
                    <p style="color: var(--text-dim); margin-bottom: 2rem;">Para começar a organizar seus estudos.</p>
                    <div class="plan-price">Gratuito</div>
                    <ul class="plan-features">
                        <li><i class="bi bi-check-circle-fill"></i> Acesso ao plano básico</li>
                        <li><i class="bi bi-check-circle-fill"></i> Foco do dia</li>
                        <li class="disabled"><i class="bi bi-x-circle"></i> Raio-X do edital (limitado)</li>
                        <li class="disabled"><i class="bi bi-x-circle"></i> Simulados básicos</li>
                    </ul>
                    <a href="cadastro.php?plano=free" class="btn btn-outline" style="width: 100%;">Começar grátis</a>
                </div>
                <!-- Premium -->
                <div class="pricing-card featured">
                    <h3 class="plan-name">PREMIUM</h3>
                    <p style="color: var(--text-dim); margin-bottom: 2rem;">Recursos completos para sua aprovação.</p>
                    <div class="plan-price">R$ 49,90<span>/mês</span></div>
                    <ul class="plan-features">
                        <li><i class="bi bi-check-circle-fill"></i> Tudo do plano Básico</li>
                        <li><i class="bi bi-check-circle-fill"></i> Índice de Aprovação</li>
                        <li><i class="bi bi-check-circle-fill"></i> Diagnóstico de falhas</li>
                        <li><i class="bi bi-check-circle-fill"></i> Simulados inteligentes</li>
                        <li><i class="bi bi-check-circle-fill"></i> Mentor IA</li>
                        <li><i class="bi bi-check-circle-fill"></i> Plano adaptativo com IA</li>
                    </ul>
                    <a href="cadastro.php?plano=premium" class="btn btn-primary" style="width: 100%;">Assinar agora</a>
                </div>
                <!-- Anual -->
                <div class="pricing-card">
                    <h3 class="plan-name">ANUAL</h3>
                    <p style="color: var(--text-dim); margin-bottom: 2rem;">Economize escolhendo o plano anual.</p>
                    <div class="plan-price">R$ 39,90<span>/mês</span></div>
                    <ul class="plan-features">
                        <li><i class="bi bi-check-circle-fill"></i> Tudo do plano Premium</li>
                        <li><i class="bi bi-check-circle-fill"></i> 2 meses grátis</li>
                        <li><i class="bi bi-check-circle-fill"></i> Relatórios avançados</li>
                        <li><i class="bi bi-check-circle-fill"></i> Suporte prioritário</li>
                    </ul>
                    <a href="cadastro.php?plano=anual" class="btn btn-outline" style="width: 100%;">Assinar agora</a>
                </div>
            </div>
            <p class="text-center" style="margin-top: 3rem; color: var(--text-dim); font-size: 0.9rem;">
                <i class="bi bi-shield-check text-neon"></i> 7 dias de garantia incondicional. Cancelou, acabou.
            </p>
        </div>
    </section>

    <!-- FAQ -->
    <section class="section-padding">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">DÚVIDAS FREQUENTES</span>
                <h2 class="section-title">Perguntas comuns</h2>
            </div>
            <div class="faq-list">
                <div class="faq-item">
                    <div class="faq-question">
                        Eu trabalho o dia todo. O HackConcursos vai funcionar para mim?
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <div class="faq-answer">
                        Sim. Nossa IA é projetada exatamente para quem não tem tempo. Em vez de ler 500 páginas, você foca nos 20% do conteúdo que representam 80% da sua nota.
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        Já assino um cursinho tradicional. Preciso do HackConcursos?
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <div class="faq-answer">
                        O cursinho te dá o material bruto; nós te damos a inteligência. O HackConcursos age como seu estrategista, filtrando o material do seu cursinho e dizendo exatamente o que estudar hoje.
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        E se o meu edital ainda não estiver aberto?
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <div class="faq-answer">
                        Esse é o melhor momento para começar. Nossa IA analisa o histórico da banca e cria um plano pré-edital focado no "núcleo duro". Quando o edital sair, você já estará muito à frente da concorrência.
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        Como eu sei que a Inteligência Artificial realmente funciona?
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <div class="faq-answer">
                        Nosso algoritmo não "chuta". Ele faz engenharia reversa nas últimas 5.000 questões da sua banca, identificando padrões de cobrança matematicamente comprovados.
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        Sou iniciante absoluto. Isso serve para mim?
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <div class="faq-answer">
                        Com certeza. O maior erro do iniciante é tentar estudar tudo e desistir. A IA cria uma trilha de aprendizado progressiva, garantindo que você construa sua base sem sobrecarga.
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        Os simulados são parecidos com a prova oficial?
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <div class="faq-answer">
                        Nossos simulados "blindados" respeitam não só o estilo da banca (Cebraspe, FGV, FCC, etc.), mas também o nível de dificuldade e o peso de cada disciplina, preparando até o seu psicológico.
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        E se eu não gostar da plataforma? Tem garantia?
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <div class="faq-answer">
                        Sim! Você tem 7 dias de garantia incondicional. Se você não sentir que sua produtividade aumentou logo na primeira semana, devolvemos 100% do seu dinheiro, sem burocracia.
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        Vou ter que baixar algum aplicativo pesado?
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <div class="faq-answer">
                        Não. O HackConcursos funciona 100% na nuvem. Você pode acessar de qualquer computador, tablet ou celular, bastando ter conexão com a internet.
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        Por que isso é melhor que montar meu próprio cronograma no Excel?
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <div class="faq-answer">
                        Um Excel não é dinâmico. Se você atrasar um dia, a planilha quebra. Nossa IA reajusta sua rota automaticamente, além de calcular sua curva de esquecimento para programar as revisões no momento exato.
                    </div>
                </div>
                <div class="faq-item">
                    <div class="faq-question">
                        O acesso é imediato após o pagamento?
                        <i class="bi bi-plus-lg"></i>
                    </div>
                    <div class="faq-answer">
                        Imediato. Assim que o pagamento for aprovado, sua central de evolução é liberada e em menos de 3 minutos seu primeiro sprint já estará na tela.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA Banner -->
    <section class="section-padding">
        <div class="container">
            <a href="cadastro.php" style="display: block; text-align: center; border-radius: var(--radius-lg); overflow: hidden; box-shadow: 0 20px 40px rgba(0,0,0,0.5); transition: transform 0.3s ease, box-shadow 0.3s ease; border: 1px solid var(--border-glass);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 25px 50px rgba(34, 197, 94, 0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 20px 40px rgba(0,0,0,0.5)'">
                <img src="assets/img/banner_hack.png" alt="A Solução Completa Para Conquistar Sua Vaga" style="width: 100%; height: auto; display: block;" onerror="this.src='https://placehold.co/1200x400/0a0a0a/22c55e?text=BANNED+CTA'">
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <img src="assets/img/logo.png" height="30" style="margin-bottom: 1rem;" onerror="this.src='https://placehold.co/150x40/000/22c55e?text=HACK+CONCURSOS'">
                    <p style="color: var(--text-dim); font-size: 0.9rem; max-width: 400px;">HackConcursos é a plataforma inteligente que auxilia você a transformar sua preparação em aprovação.</p>
                </div>
                <div class="footer-col" style="text-align: right; display: flex; flex-direction: column; justify-content: flex-end; align-items: flex-end;">
                    <div>
                        <span style="color: var(--text-dim); font-size: 0.9rem; margin-left: 1rem;">Termos de Uso</span>
                        <span style="color: var(--text-dim); font-size: 0.9rem; margin-left: 1rem;">Privacidade</span>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                &copy; <?= date('Y') ?> HackConcursos. Todos os direitos reservados.
            </div>
        </div>
    </footer>

    <!-- WhatsApp Floating Button -->
    <a href="https://wa.me/5511999999999" target="_blank" class="whatsapp-float">
        <i class="bi bi-whatsapp"></i>
    </a>

    <script>
        // Navbar Scroll Effect
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Intersection Observer for Animations
        const observerOptions = {
            root: null,
            rootMargin: '0px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target); // Animate only once
                }
            });
        }, observerOptions);

        document.querySelectorAll('.animate-on-scroll').forEach((el) => {
            observer.observe(el);
        });

        // Removed 3D Tilt Effect

        // Bento Grid Mouse Glow Effect
        document.querySelectorAll('.bento-card').forEach(card => {
            card.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                card.style.setProperty('--mouse-x', `${x}px`);
                card.style.setProperty('--mouse-y', `${y}px`);
            });
        });

        // FAQ Accordion
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const item = question.parentElement;
                const answer = question.nextElementSibling;
                const icon = question.querySelector('i');
                
                const isOpen = answer.style.display === 'block';
                
                // Fechar todos
                document.querySelectorAll('.faq-answer').forEach(a => a.style.display = 'none');
                document.querySelectorAll('.faq-item i').forEach(i => {
                    i.classList.remove('bi-dash-lg');
                    i.classList.add('bi-plus-lg');
                });
                
                if (!isOpen) {
                    answer.style.display = 'block';
                    icon.classList.remove('bi-plus-lg');
                    icon.classList.add('bi-dash-lg');
                }
            });
        });

        // Smooth Scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>
