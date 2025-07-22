<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title>{{ config('app.name', 'LaraCity AI') }} - Where Laravel Meets AI Intelligence</title>
    <meta name="description" content="Transform from AI curious to AI capable. Learn to build production-ready Laravel applications with AI-powered features through our comprehensive NYC 311 complaint processing tutorial series.">
    <meta name="keywords" content="Laravel, AI, Tutorial, Machine Learning, PHP, Web Development, NYC 311, OpenAI, LaraCity">
    <meta name="author" content="{{ config('app.name', 'LaraCity AI') }}">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url('/') }}">
    
    <!-- Open Graph Meta Tags (Facebook & LinkedIn) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ config('app.name', 'LaraCity AI') }} - Where Laravel Meets AI Intelligence">
    <meta property="og:description" content="Transform from AI curious to AI capable by building a real-world NYC 311 complaint processing system. Master Laravel + OpenAI integration through hands-on development.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:site_name" content="{{ config('app.name', 'LaraCity AI') }}">
    <meta property="og:image" content="{{ asset('images/og-image.jpg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="LaraCity AI - Laravel meets AI Intelligence">
    <meta property="og:locale" content="en_US">
    
    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ config('app.name', 'LaraCity AI') }} - Where Laravel Meets AI Intelligence">
    <meta name="twitter:description" content="Transform from AI curious to AI capable. Build production-ready Laravel applications with AI-powered features.">
    <meta name="twitter:image" content="{{ asset('images/twitter-image.jpg') }}">
    <meta name="twitter:site" content="@laracity">
    <meta name="twitter:creator" content="@laracity">
    
    <!-- Favicons -->
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    
    <!-- Fonts - Inter for Professional Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS with Custom Styles for Welcome Page ONLY -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        /* CSS Custom Properties for Design System - WELCOME PAGE ONLY */
        :root {
            /* Colors - Professional Dark Theme */
            --welcome-color-primary: #6366f1;
            --welcome-color-primary-light: #818cf8;
            --welcome-color-primary-dark: #4f46e5;
            --welcome-color-secondary: #8b5cf6;
            --welcome-color-accent: #06b6d4;
            --welcome-color-success: #10b981;
            --welcome-color-warning: #f59e0b;
            --welcome-color-error: #ef4444;
            
            /* Neutral Colors - Optimized for Professional Look */
            --welcome-color-white: #ffffff;
            --welcome-color-gray-50: #f9fafb;
            --welcome-color-gray-100: #f3f4f6;
            --welcome-color-gray-200: #e5e7eb;
            --welcome-color-gray-300: #d1d5db;
            --welcome-color-gray-400: #9ca3af;
            --welcome-color-gray-500: #6b7280;
            --welcome-color-gray-600: #4b5563;
            --welcome-color-gray-700: #374151;
            --welcome-color-gray-800: #1f2937;
            --welcome-color-gray-900: #111827;
            --welcome-color-gray-950: #030712;
            
            /* Background System - Rich Dark Gradients */
            --welcome-bg-primary: linear-gradient(135deg, #0a0e1a 0%, #1a1f36 20%, #2d1b69 40%, #1e1b4b 60%, #0f172a 80%, #030712 100%);
            --welcome-bg-gradient-primary: linear-gradient(135deg, #6366f1 0%, #8b5cf6 50%, #06b6d4 100%);
            --welcome-bg-gradient-secondary: linear-gradient(135deg, #8b5cf6 0%, #d946ef 100%);
            --welcome-bg-card: rgba(15, 23, 42, 0.9);
            --welcome-bg-card-hover: rgba(15, 23, 42, 0.95);
            --welcome-bg-overlay: rgba(0, 0, 0, 0.6);
            
            /* Typography - Professional UI Design Standards */
            --welcome-font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            
            /* Font Sizes - Consistent Scale */
            --welcome-font-size-xs: 0.75rem;      /* 12px */
            --welcome-font-size-sm: 0.875rem;     /* 14px */
            --welcome-font-size-base: 1rem;       /* 16px */
            --welcome-font-size-lg: 1.125rem;     /* 18px */
            --welcome-font-size-xl: 1.25rem;      /* 20px */
            --welcome-font-size-2xl: 1.5rem;      /* 24px */
            --welcome-font-size-3xl: 1.875rem;    /* 30px */
            --welcome-font-size-4xl: 2.25rem;     /* 36px */
            --welcome-font-size-5xl: 3rem;        /* 48px */
            --welcome-font-size-6xl: 3.75rem;     /* 60px */
            --welcome-font-size-7xl: 4.5rem;      /* 72px */
            
            /* Professional Typography Settings */
            --welcome-letter-spacing-tight: -0.025em;
            --welcome-letter-spacing-normal: 0em;
            --welcome-letter-spacing-wide: 0.025em;
            
            /* Line Heights - Optimized for Readability */
            --welcome-line-height-none: 1;
            --welcome-line-height-tight: 1.25;
            --welcome-line-height-snug: 1.375;
            --welcome-line-height-normal: 1.5;
            --welcome-line-height-relaxed: 1.625;
            --welcome-line-height-loose: 2;
            
            /* Font Weights */
            --welcome-font-weight-light: 300;
            --welcome-font-weight-normal: 400;
            --welcome-font-weight-medium: 500;
            --welcome-font-weight-semibold: 600;
            --welcome-font-weight-bold: 700;
            --welcome-font-weight-extrabold: 800;
            --welcome-font-weight-black: 900;
            
            /* Spacing System (8px base) */
            --welcome-space-px: 1px;
            --welcome-space-0-5: 0.125rem;   /* 2px */
            --welcome-space-1: 0.25rem;      /* 4px */
            --welcome-space-1-5: 0.375rem;   /* 6px */
            --welcome-space-2: 0.5rem;       /* 8px */
            --welcome-space-2-5: 0.625rem;   /* 10px */
            --welcome-space-3: 0.75rem;      /* 12px */
            --welcome-space-3-5: 0.875rem;   /* 14px */
            --welcome-space-4: 1rem;         /* 16px */
            --welcome-space-5: 1.25rem;      /* 20px */
            --welcome-space-6: 1.5rem;       /* 24px */
            --welcome-space-7: 1.75rem;      /* 28px */
            --welcome-space-8: 2rem;         /* 32px */
            --welcome-space-9: 2.25rem;      /* 36px */
            --welcome-space-10: 2.5rem;      /* 40px */
            --welcome-space-11: 2.75rem;     /* 44px */
            --welcome-space-12: 3rem;        /* 48px */
            --welcome-space-14: 3.5rem;      /* 56px */
            --welcome-space-16: 4rem;        /* 64px */
            --welcome-space-20: 5rem;        /* 80px */
            --welcome-space-24: 6rem;        /* 96px */
            --welcome-space-28: 7rem;        /* 112px */
            --welcome-space-32: 8rem;        /* 128px */
            
            /* Border Radius */
            --welcome-radius-none: 0;
            --welcome-radius-sm: 0.125rem;    /* 2px */
            --welcome-radius-md: 0.375rem;    /* 6px */
            --welcome-radius-lg: 0.5rem;      /* 8px */
            --welcome-radius-xl: 0.75rem;     /* 12px */
            --welcome-radius-2xl: 1rem;       /* 16px */
            --welcome-radius-3xl: 1.5rem;     /* 24px */
            --welcome-radius-full: 9999px;
            
            /* Shadows - Professional Depth */
            --welcome-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --welcome-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --welcome-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --welcome-shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --welcome-shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --welcome-shadow-inner: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
            --welcome-shadow-glow: 0 0 50px rgba(99, 102, 241, 0.3);
            --welcome-shadow-colored: 0 10px 30px -5px rgba(99, 102, 241, 0.4);
            
            /* Transitions */
            --welcome-transition-none: none;
            --welcome-transition-all: all 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --welcome-transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --welcome-transition-normal: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            --welcome-transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Base Styles - Welcome Page Only */
        * {
            box-sizing: border-box;
        }
        
        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--welcome-font-family);
            font-size: var(--welcome-font-size-base);
            font-weight: var(--welcome-font-weight-normal);
            line-height: var(--welcome-line-height-normal);
            color: var(--welcome-color-white);
            background: #030712;
            background-image: var(--welcome-bg-primary);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 80% 70%, rgba(139, 92, 246, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 40% 90%, rgba(6, 182, 212, 0.08) 0%, transparent 40%);
            pointer-events: none;
            z-index: 0;
        }

        /* Professional Typography - Welcome Page Only */
        .welcome-h1 { 
            font-size: clamp(var(--welcome-font-size-5xl), 8vw, var(--welcome-font-size-7xl)); 
            font-weight: var(--welcome-font-weight-black);
            line-height: var(--welcome-line-height-none);
            letter-spacing: var(--welcome-letter-spacing-tight);
            margin-bottom: var(--welcome-space-6);
            color: var(--welcome-color-white);
        }
        
        .welcome-h2 { 
            font-size: var(--welcome-font-size-4xl); 
            font-weight: var(--welcome-font-weight-extrabold);
            line-height: var(--welcome-line-height-tight);
            letter-spacing: var(--welcome-letter-spacing-tight);
            margin-bottom: var(--welcome-space-5);
            color: var(--welcome-color-white);
        }
        
        .welcome-h3 { 
            font-size: var(--welcome-font-size-2xl); 
            font-weight: var(--welcome-font-weight-bold);
            line-height: var(--welcome-line-height-tight);
            margin-bottom: var(--welcome-space-4);
            color: var(--welcome-color-white);
        }
        
        .welcome-h4 { 
            font-size: var(--welcome-font-size-xl); 
            font-weight: var(--welcome-font-weight-semibold);
            line-height: var(--welcome-line-height-snug);
            margin-bottom: var(--welcome-space-3);
            color: var(--welcome-color-white);
        }

        .welcome-p {
            margin-bottom: var(--welcome-space-4);
            color: var(--welcome-color-gray-300);
            line-height: var(--welcome-line-height-relaxed);
        }

        .welcome-p-large {
            font-size: var(--welcome-font-size-lg);
            line-height: var(--welcome-line-height-relaxed);
            color: var(--welcome-color-gray-200);
        }

        /* Navigation Styles - Welcome Page Only */
        .welcome-nav {
            position: relative;
            background: transparent;
            background-image: var(--welcome-bg-primary);
            backdrop-filter: blur(20px);
            padding: var(--welcome-space-6) 0;
            border-bottom: none;
            z-index: 100;
        }

        .welcome-nav__container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--welcome-space-6);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .welcome-nav__brand {
            display: flex;
            align-items: center;
            gap: var(--welcome-space-3);
        }

        .welcome-nav__logo {
            width: 40px;
            height: 40px;
            background: var(--welcome-bg-gradient-primary);
            border-radius: var(--welcome-radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: var(--welcome-font-weight-black);
            font-size: var(--welcome-font-size-lg);
            color: var(--welcome-color-white);
            box-shadow: var(--welcome-shadow-lg);
        }

        .welcome-nav__title {
            font-size: var(--welcome-font-size-xl);
            font-weight: var(--welcome-font-weight-bold);
            color: var(--welcome-color-white);
            letter-spacing: var(--welcome-letter-spacing-tight);
        }

        .welcome-nav__links {
            display: flex;
            align-items: center;
            gap: var(--welcome-space-6);
        }

        .welcome-nav__link {
            color: var(--welcome-color-gray-300);
            text-decoration: none;
            font-weight: var(--welcome-font-weight-medium);
            font-size: var(--welcome-font-size-sm);
            transition: var(--welcome-transition-fast);
            position: relative;
        }

        .welcome-nav__link:hover {
            color: var(--welcome-color-primary-light);
        }

        /* Hero Section - Welcome Page Only */
        .welcome-hero {
            position: relative;
            min-height: 90vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
            padding: var(--welcome-space-20) var(--welcome-space-6) var(--welcome-space-16);
        }

        .welcome-hero__container {
            max-width: 1000px;
            margin: 0 auto;
            z-index: 10;
            position: relative;
        }
        
        .welcome-hero > * {
            position: relative;
            z-index: 10;
        }

        .welcome-hero__badge {
            display: inline-flex;
            align-items: center;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: var(--welcome-radius-full);
            padding: var(--welcome-space-2) var(--welcome-space-6);
            margin-bottom: var(--welcome-space-8);
            animation: welcome-float 3s ease-in-out infinite;
            backdrop-filter: blur(10px);
        }

        .welcome-hero__badge-text {
            font-size: var(--welcome-font-size-sm);
            font-weight: var(--welcome-font-weight-semibold);
            color: var(--welcome-color-primary-light);
            letter-spacing: var(--welcome-letter-spacing-wide);
        }

        .welcome-hero__title {
            font-size: clamp(var(--welcome-font-size-5xl), 8vw, var(--welcome-font-size-7xl));
            font-weight: var(--welcome-font-weight-black);
            line-height: var(--welcome-line-height-none);
            letter-spacing: var(--welcome-letter-spacing-tight);
            margin-bottom: var(--welcome-space-8);
            background: linear-gradient(135deg, var(--welcome-color-white) 0%, var(--welcome-color-gray-200) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-hero__title-accent {
            background: var(--welcome-bg-gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-hero__subtitle {
            font-size: var(--welcome-font-size-xl);
            font-weight: var(--welcome-font-weight-medium);
            color: var(--welcome-color-gray-300);
            line-height: var(--welcome-line-height-relaxed);
            margin-bottom: var(--welcome-space-8);
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .welcome-hero__description {
            font-size: var(--welcome-font-size-lg);
            color: var(--welcome-color-gray-400);
            line-height: var(--welcome-line-height-relaxed);
            margin-bottom: var(--welcome-space-12);
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .welcome-hero__cta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--welcome-space-4);
            justify-content: center;
            align-items: center;
        }

        /* Button Components - Welcome Page Only */
        .welcome-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--welcome-space-2);
            padding: var(--welcome-space-4) var(--welcome-space-8);
            border: none;
            border-radius: var(--welcome-radius-xl);
            font-family: var(--welcome-font-family);
            font-weight: var(--welcome-font-weight-semibold);
            font-size: var(--welcome-font-size-base);
            text-decoration: none;
            cursor: pointer;
            transition: var(--welcome-transition-normal);
            position: relative;
            overflow: hidden;
            white-space: nowrap;
            outline: none;
        }

        .welcome-btn--primary {
            background: var(--welcome-bg-gradient-primary);
            color: var(--welcome-color-white);
            box-shadow: var(--welcome-shadow-colored);
            font-size: var(--welcome-font-size-lg);
            padding: var(--welcome-space-5) var(--welcome-space-10);
            font-weight: var(--welcome-font-weight-bold);
        }

        .welcome-btn--primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--welcome-shadow-2xl), var(--welcome-shadow-glow);
        }

        .welcome-btn--secondary {
            background: rgba(99, 102, 241, 0.1);
            color: var(--welcome-color-primary-light);
            border: 2px solid rgba(99, 102, 241, 0.3);
            backdrop-filter: blur(10px);
        }

        .welcome-btn--secondary:hover {
            background: rgba(99, 102, 241, 0.2);
            border-color: var(--welcome-color-primary);
            transform: translateY(-1px);
        }

        .welcome-btn--outline {
            background: transparent;
            color: var(--welcome-color-gray-300);
            border: 2px solid var(--welcome-color-gray-600);
            backdrop-filter: blur(10px);
        }

        .welcome-btn--outline:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--welcome-color-gray-400);
            color: var(--welcome-color-white);
            transform: translateY(-1px);
        }

        /* Chapters Section - Welcome Page Only */
        .welcome-chapters {
            padding: var(--welcome-space-24) var(--welcome-space-6);
            position: relative;
            background: rgba(3, 7, 18, 0.4);
            backdrop-filter: blur(10px);
        }

        .welcome-chapters__container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome-chapters__header {
            text-align: center;
            margin-bottom: var(--welcome-space-16);
        }

        .welcome-chapters__title {
            font-size: var(--welcome-font-size-4xl);
            font-weight: var(--welcome-font-weight-extrabold);
            line-height: var(--welcome-line-height-tight);
            letter-spacing: var(--welcome-letter-spacing-tight);
            margin-bottom: var(--welcome-space-6);
            background: linear-gradient(135deg, var(--welcome-color-white) 0%, var(--welcome-color-primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-chapters__subtitle {
            font-size: var(--welcome-font-size-xl);
            color: var(--welcome-color-gray-300);
            line-height: var(--welcome-line-height-relaxed);
            max-width: 600px;
            margin: 0 auto;
        }

        .welcome-chapters__grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: var(--welcome-space-6);
            margin-top: var(--welcome-space-12);
        }

        /* Chapter Card Component */
        .welcome-chapter-card {
            background: var(--welcome-bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: var(--welcome-radius-2xl);
            padding: var(--welcome-space-8);
            transition: var(--welcome-transition-normal);
            position: relative;
            overflow: hidden;
        }

        .welcome-chapter-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--welcome-bg-gradient-primary);
            opacity: 0;
            transition: var(--welcome-transition-normal);
        }

        .welcome-chapter-card:hover::before {
            opacity: 1;
        }

        .welcome-chapter-card:hover {
            background: var(--welcome-bg-card-hover);
            transform: translateY(-4px);
            box-shadow: var(--welcome-shadow-xl);
            border-color: rgba(99, 102, 241, 0.4);
        }

        .welcome-chapter-card--bonus {
            border-color: rgba(6, 182, 212, 0.3);
            background: rgba(31, 41, 55, 0.9);
            position: relative;
        }

        .welcome-chapter-card--bonus::after {
            content: 'BONUS';
            position: absolute;
            top: var(--welcome-space-4);
            right: var(--welcome-space-4);
            background: var(--welcome-color-accent);
            color: var(--welcome-color-gray-900);
            font-size: var(--welcome-font-size-xs);
            font-weight: var(--welcome-font-weight-bold);
            padding: var(--welcome-space-1) var(--welcome-space-3);
            border-radius: var(--welcome-radius-md);
            letter-spacing: var(--welcome-letter-spacing-wide);
        }

        .welcome-chapter-card__header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--welcome-space-6);
        }

        .welcome-chapter-card__number {
            font-size: var(--welcome-font-size-3xl);
            font-weight: var(--welcome-font-weight-black);
            color: var(--welcome-color-primary);
            background: rgba(99, 102, 241, 0.1);
            width: 60px;
            height: 60px;
            border-radius: var(--welcome-radius-2xl);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(99, 102, 241, 0.2);
        }

        .welcome-chapter-card__type {
            font-size: var(--welcome-font-size-sm);
            font-weight: var(--welcome-font-weight-semibold);
            color: var(--welcome-color-gray-400);
            text-transform: uppercase;
            letter-spacing: var(--welcome-letter-spacing-wide);
        }

        .welcome-chapter-card__title {
            font-size: var(--welcome-font-size-xl);
            font-weight: var(--welcome-font-weight-bold);
            line-height: var(--welcome-line-height-snug);
            margin-bottom: var(--welcome-space-4);
            color: var(--welcome-color-white);
        }

        .welcome-chapter-card__description {
            color: var(--welcome-color-gray-300);
            line-height: var(--welcome-line-height-relaxed);
            margin-bottom: var(--welcome-space-6);
        }

        .welcome-chapter-card__skills {
            display: flex;
            flex-wrap: wrap;
            gap: var(--welcome-space-2);
        }

        .welcome-skill-tag {
            background: rgba(99, 102, 241, 0.1);
            color: var(--welcome-color-primary-light);
            font-size: var(--welcome-font-size-xs);
            font-weight: var(--welcome-font-weight-semibold);
            padding: var(--welcome-space-2) var(--welcome-space-3);
            border-radius: var(--welcome-radius-md);
            border: 1px solid rgba(99, 102, 241, 0.2);
            letter-spacing: var(--welcome-letter-spacing-wide);
        }

        /* Technology Partners Section */
        .welcome-hero__partners {
            margin-top: var(--welcome-space-16);
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .welcome-partners__label {
            font-size: var(--welcome-font-size-sm);
            font-weight: var(--welcome-font-weight-semibold);
            color: var(--welcome-color-gray-400);
            text-transform: uppercase;
            letter-spacing: var(--welcome-letter-spacing-wide);
            margin-bottom: var(--welcome-space-8);
        }

        .welcome-partners__grid {
            display: flex;
            justify-content: center;
            gap: var(--welcome-space-12);
            flex-wrap: wrap;
        }

        .welcome-partner-link {
            display: flex;
            align-items: center;
            gap: var(--welcome-space-4);
            padding: var(--welcome-space-6) var(--welcome-space-8);
            background: rgba(31, 41, 55, 0.6);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: var(--welcome-radius-2xl);
            text-decoration: none;
            transition: var(--welcome-transition-normal);
            min-width: 200px;
        }

        .welcome-partner-link:hover {
            background: rgba(31, 41, 55, 0.9);
            border-color: var(--welcome-color-primary);
            transform: translateY(-4px);
            box-shadow: var(--welcome-shadow-xl);
        }

        .welcome-partner-name {
            font-size: var(--welcome-font-size-lg);
            font-weight: var(--welcome-font-weight-bold);
            color: var(--welcome-color-white);
            margin-bottom: var(--welcome-space-1);
        }

        .welcome-partner-description {
            font-size: var(--welcome-font-size-sm);
            color: var(--welcome-color-gray-400);
            margin-bottom: 0;
        }

        /* Final CTA Section */
        .welcome-final-cta {
            padding: var(--welcome-space-24) var(--welcome-space-6);
            text-align: center;
            position: relative;
        }

        .welcome-final-cta__container {
            max-width: 800px;
            margin: 0 auto;
        }

        .welcome-final-cta__title {
            font-size: var(--welcome-font-size-4xl);
            font-weight: var(--welcome-font-weight-extrabold);
            line-height: var(--welcome-line-height-tight);
            letter-spacing: var(--welcome-letter-spacing-tight);
            margin-bottom: var(--welcome-space-6);
            background: var(--welcome-bg-gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-final-cta__subtitle {
            font-size: var(--welcome-font-size-xl);
            color: var(--welcome-color-gray-300);
            line-height: var(--welcome-line-height-relaxed);
            margin-bottom: var(--welcome-space-12);
        }

        .welcome-final-cta__buttons {
            display: flex;
            flex-wrap: wrap;
            gap: var(--welcome-space-6);
            justify-content: center;
        }

        /* Footer */
        .welcome-footer {
            background: rgba(3, 7, 18, 0.98);
            border-top: 1px solid rgba(99, 102, 241, 0.2);
            padding: var(--welcome-space-16) var(--welcome-space-6) var(--welcome-space-8);
            backdrop-filter: blur(20px);
        }

        .welcome-footer__container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .welcome-footer__content {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: var(--welcome-space-12);
            margin-bottom: var(--welcome-space-12);
        }

        .welcome-footer__brand {
            display: flex;
            gap: var(--welcome-space-4);
            align-items: flex-start;
        }

        .welcome-footer__logo {
            width: 48px;
            height: 48px;
            background: var(--welcome-bg-gradient-primary);
            border-radius: var(--welcome-radius-2xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: var(--welcome-font-weight-black);
            font-size: var(--welcome-font-size-xl);
            color: var(--welcome-color-white);
            box-shadow: var(--welcome-shadow-lg);
            flex-shrink: 0;
        }

        .welcome-footer__title {
            font-size: var(--welcome-font-size-xl);
            font-weight: var(--welcome-font-weight-bold);
            margin-bottom: var(--welcome-space-2);
            color: var(--welcome-color-white);
        }

        .welcome-footer__description {
            color: var(--welcome-color-gray-400);
            line-height: var(--welcome-line-height-relaxed);
            max-width: 400px;
            margin-bottom: 0;
        }

        .welcome-footer__links {
            display: flex;
            gap: var(--welcome-space-12);
        }

        .welcome-footer__link-group {
            display: flex;
            flex-direction: column;
            gap: var(--welcome-space-3);
        }

        .welcome-footer__link-title {
            font-size: var(--welcome-font-size-sm);
            font-weight: var(--welcome-font-weight-bold);
            color: var(--welcome-color-white);
            margin-bottom: var(--welcome-space-2);
            text-transform: uppercase;
            letter-spacing: var(--welcome-letter-spacing-wide);
        }

        .welcome-footer__link {
            color: var(--welcome-color-gray-400);
            text-decoration: none;
            font-size: var(--welcome-font-size-sm);
            transition: var(--welcome-transition-fast);
        }

        .welcome-footer__link:hover {
            color: var(--welcome-color-primary-light);
        }

        .welcome-footer__bottom {
            text-align: center;
            padding-top: var(--welcome-space-8);
            border-top: 1px solid rgba(99, 102, 241, 0.1);
        }

        .welcome-footer__copyright {
            color: var(--welcome-color-gray-500);
            font-size: var(--welcome-font-size-sm);
            margin-bottom: 0;
            line-height: var(--welcome-line-height-relaxed);
        }

        /* Background Animation Elements */
        .welcome-hero__bg-elements {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
            z-index: 1;
        }

        .welcome-hero__circle {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, transparent 70%);
            animation: welcome-float 6s ease-in-out infinite;
        }

        .welcome-hero__circle--1 {
            width: 400px;
            height: 400px;
            top: 10%;
            left: -10%;
            animation-delay: 0s;
        }

        .welcome-hero__circle--2 {
            width: 300px;
            height: 300px;
            top: 60%;
            right: -5%;
            animation-delay: 2s;
        }

        .welcome-hero__circle--3 {
            width: 200px;
            height: 200px;
            bottom: 20%;
            left: 50%;
            animation-delay: 4s;
        }

        /* Animations - Welcome Page Only */
        @keyframes welcome-float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        /* Responsive Design - Mobile First Approach - Welcome Page Only */
        @media (max-width: 640px) {
            .welcome-nav__container {
                padding: 0 var(--welcome-space-4);
            }
            
            .welcome-nav__links {
                gap: var(--welcome-space-3);
            }
            
            .welcome-hero {
                padding: var(--welcome-space-16) var(--welcome-space-4) var(--welcome-space-12);
            }
            
            .welcome-hero__cta {
                flex-direction: column;
                align-items: stretch;
            }
            
            .welcome-hero__cta .welcome-btn {
                justify-content: center;
            }
            
            .welcome-partners__grid {
                flex-direction: column;
                gap: var(--welcome-space-4);
                align-items: center;
            }
            
            .welcome-partner-link {
                min-width: 250px;
            }
            
            .welcome-chapters__grid {
                grid-template-columns: 1fr;
                gap: var(--welcome-space-4);
            }
            
            .welcome-chapter-card {
                padding: var(--welcome-space-6);
            }
            
            .welcome-final-cta__buttons {
                flex-direction: column;
                align-items: stretch;
            }
            
            .welcome-footer__content {
                grid-template-columns: 1fr;
                gap: var(--welcome-space-8);
                text-align: center;
            }
            
            .welcome-footer__links {
                justify-content: center;
                gap: var(--welcome-space-8);
            }
        }

        @media (min-width: 641px) and (max-width: 768px) {
            .welcome-chapters__grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Focus Management for Accessibility - Welcome Page Only */
        .welcome-btn:focus,
        .welcome-nav__link:focus,
        .welcome-footer__link:focus {
            outline: 2px solid var(--welcome-color-primary);
            outline-offset: 2px;
        }

        /* Print Styles */
        @media print {
            .welcome-nav,
            .welcome-hero__bg-elements,
            .welcome-final-cta,
            .welcome-footer {
                display: none;
            }
            
            body {
                background: white;
                color: black;
            }
            
            .welcome-hero,
            .welcome-chapters {
                background: white;
                color: black;
            }
        }

        /* Reduced Motion Support */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
                scroll-behavior: auto !important;
            }
            
            .welcome-hero__circle {
                animation: none;
            }
            
            .welcome-hero__badge {
                animation: none;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="welcome-nav">
        <div class="welcome-nav__container">
            <div class="welcome-nav__brand">
                <div class="welcome-nav__logo">LC</div>
                <span class="welcome-nav__title">{{ config('app.name', 'LaraCity AI') }}</span>
            </div>
            <div class="welcome-nav__links">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="welcome-btn welcome-btn--primary">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M3 4a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM9 4a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1V4zM15 4a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2zM9 10a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2zM15 10a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2zM3 16a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1v-2zM9 16a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2zM15 16a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 01-1 1h-2a1 1 0 01-1-1v-2z" fill="currentColor"/>
                            </svg>
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="welcome-nav__link">Sign In</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="welcome-btn welcome-btn--secondary">Get Started</a>
                        @endif
                    @endauth
                @endif
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="welcome-hero">
        <div class="welcome-hero__container">
            <div class="welcome-hero__badge">
                <span class="welcome-hero__badge-text">🚀 Production-Ready AI Tutorial Series</span>
            </div>
            
            <h1 class="welcome-hero__title">
                Welcome to <span class="welcome-hero__title-accent">{{ config('app.name', 'LaraCity') }}</span><br>
                Where Laravel Meets AI Intelligence
            </h1>
            
            <p class="welcome-hero__subtitle">
                Transform from <strong>AI curious</strong> to <strong>AI capable</strong> by building a real-world NYC 311 complaint processing system. Master Laravel + LangChain integration through hands-on development of production-ready features.
            </p>
            
            <div class="welcome-hero__description">
                <p>Build an intelligent city management platform that processes thousands of citizen complaints using AI-powered analysis, pattern detection, and natural language conversations—just like the real NYC 311 system.</p>
            </div>
            
            <div class="welcome-hero__cta">
                @auth
                    <a href="{{ url('/dashboard') }}" class="welcome-btn welcome-btn--primary">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Start Tutorial Now
                    </a>
                @else
                    <a href="{{ route('register') }}" class="welcome-btn welcome-btn--primary">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Start Tutorial Now
                    </a>
                @endauth
                
                <a href="https://github.com/bmadigan/LaraCity" target="_blank" class="welcome-btn welcome-btn--secondary" rel="noopener noreferrer">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M10 0C4.477 0 0 4.584 0 10.253c0 4.529 2.865 8.371 6.839 9.728.5.094.682-.223.682-.494 0-.243-.009-.888-.013-1.743-2.782.621-3.369-1.377-3.369-1.377-.454-1.182-1.11-1.497-1.11-1.497-.908-.637.069-.624.069-.624 1.003.073 1.531 1.058 1.531 1.058.892 1.568 2.341 1.115 2.91.853.092-.664.35-1.115.636-1.371-2.22-.259-4.555-1.139-4.555-5.068 0-1.119.39-2.035 1.029-2.751-.103-.259-.446-1.302.098-2.714 0 0 .84-.276 2.75 1.051A9.367 9.367 0 0 1 10 4.958a9.36 9.36 0 0 1 2.504.346c1.909-1.327 2.747-1.051 2.747-1.051.546 1.412.202 2.455.1 2.714.64.716 1.027 1.632 1.027 2.751 0 3.939-2.339 4.805-4.566 5.058.359.317.678.943.678 1.901 0 1.374-.012 2.48-.012 2.814 0 .274.18.594.688.493C17.137 18.62 20 14.781 20 10.253 20 4.584 15.523 0 10 0Z" fill="currentColor"/>
                    </svg>
                    View GitHub
                </a>
                
                <button class="welcome-btn welcome-btn--outline" onclick="alert('🚀 Interactive demo coming soon! Sign up to be notified when it\'s ready.')">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M10 2L3 7v11h4v-6h6v6h4V7l-7-5z" fill="currentColor"/>
                        <circle cx="15" cy="5" r="2" fill="currentColor"/>
                    </svg>
                    View Demo
                </button>
            </div>
        </div>
        
        <!-- Technology Partners Section -->
        <div class="welcome-hero__partners">
            <p class="welcome-partners__label">Built with industry-leading technologies</p>
            <div class="welcome-partners__grid">
                <a href="https://laravel.com" target="_blank" class="welcome-partner-link" rel="noopener noreferrer">
                    <div class="welcome-partner-logo">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                            <path d="M32.5 12.5L20 5L7.5 12.5L20 20L32.5 12.5Z" fill="#FF2D20"/>
                            <path d="M7.5 15L20 22.5V35L7.5 27.5V15Z" fill="#FF2D20" opacity="0.8"/>
                            <path d="M32.5 15L20 22.5V35L32.5 27.5V15Z" fill="#FF2D20" opacity="0.6"/>
                        </svg>
                    </div>
                    <div class="welcome-partner-info">
                        <h4 class="welcome-partner-name">Laravel</h4>
                        <p class="welcome-partner-description">PHP Web Framework</p>
                    </div>
                </a>
                
                <a href="https://python.langchain.com" target="_blank" class="welcome-partner-link" rel="noopener noreferrer">
                    <div class="welcome-partner-logo">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                            <rect width="40" height="40" rx="8" fill="url(#langchainGradient)"/>
                            <path d="M20 8l-8 6v16h4v-8h8v8h4V14l-8-6z" fill="white"/>
                            <circle cx="30" cy="12" r="3" fill="#10B981"/>
                            <defs>
                                <linearGradient id="langchainGradient" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse">
                                    <stop stop-color="#1F2937"/>
                                    <stop offset="1" stop-color="#374151"/>
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                    <div class="welcome-partner-info">
                        <h4 class="welcome-partner-name">LangChain</h4>
                        <p class="welcome-partner-description">AI Development Framework</p>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Hero Background Elements -->
        <div class="welcome-hero__bg-elements">
            <div class="welcome-hero__circle welcome-hero__circle--1"></div>
            <div class="welcome-hero__circle welcome-hero__circle--2"></div>
            <div class="welcome-hero__circle welcome-hero__circle--3"></div>
        </div>
    </header>

    <!-- Chapters Section -->
    <section class="welcome-chapters">
        <div class="welcome-chapters__container">
            <div class="welcome-chapters__header">
                <h2 class="welcome-chapters__title">Complete Learning Journey</h2>
                <p class="welcome-chapters__subtitle">8 comprehensive chapters + 2 bonus modules to master AI-powered Laravel development</p>
            </div>
            
            <div class="welcome-chapters__grid">
                <!-- Core Chapters -->
                <div class="welcome-chapter-card">
                    <div class="welcome-chapter-card__header">
                        <span class="welcome-chapter-card__number">01</span>
                        <span class="welcome-chapter-card__type">Foundation</span>
                    </div>
                    <h3 class="welcome-chapter-card__title">Setting Up LaraCity</h3>
                    <p class="welcome-chapter-card__description">Bootstrap your Laravel application with modern architecture, database design, and development environment setup.</p>
                    <div class="welcome-chapter-card__skills">
                        <span class="welcome-skill-tag">Laravel Setup</span>
                        <span class="welcome-skill-tag">Database Design</span>
                    </div>
                </div>

                <div class="welcome-chapter-card">
                    <div class="welcome-chapter-card__header">
                        <span class="welcome-chapter-card__number">02</span>
                        <span class="welcome-chapter-card__type">Core</span>
                    </div>
                    <h3 class="welcome-chapter-card__title">Building the Complaint System</h3>
                    <p class="welcome-chapter-card__description">Create robust CRUD operations for managing citizen complaints with proper validation and data integrity.</p>
                    <div class="welcome-chapter-card__skills">
                        <span class="welcome-skill-tag">Eloquent ORM</span>
                        <span class="welcome-skill-tag">Form Validation</span>
                    </div>
                </div>

                <div class="welcome-chapter-card">
                    <div class="welcome-chapter-card__header">
                        <span class="welcome-chapter-card__number">03</span>
                        <span class="welcome-chapter-card__type">AI Integration</span>
                    </div>
                    <h3 class="welcome-chapter-card__title">LangChain API Integration</h3>
                    <p class="welcome-chapter-card__description">Connect your Laravel app to LangChain's powerful APIs for intelligent text analysis and automated responses.</p>
                    <div class="welcome-chapter-card__skills">
                        <span class="welcome-skill-tag">LangChain API</span>
                        <span class="welcome-skill-tag">HTTP Clients</span>
                    </div>
                </div>

                <div class="welcome-chapter-card">
                    <div class="welcome-chapter-card__header">
                        <span class="welcome-chapter-card__number">04</span>
                        <span class="welcome-chapter-card__type">AI Features</span>
                    </div>
                    <h3 class="welcome-chapter-card__title">Intelligent Complaint Analysis</h3>
                    <p class="welcome-chapter-card__description">Implement AI-powered sentiment analysis, categorization, and priority detection for incoming complaints.</p>
                    <div class="welcome-chapter-card__skills">
                        <span class="welcome-skill-tag">Sentiment Analysis</span>
                        <span class="welcome-skill-tag">Auto-categorization</span>
                    </div>
                </div>

                <div class="welcome-chapter-card">
                    <div class="welcome-chapter-card__header">
                        <span class="welcome-chapter-card__number">05</span>
                        <span class="welcome-chapter-card__type">Advanced AI</span>
                    </div>
                    <h3 class="welcome-chapter-card__title">Pattern Detection & Insights</h3>
                    <p class="welcome-chapter-card__description">Build sophisticated algorithms to identify complaint patterns, trends, and correlations across city data.</p>
                    <div class="welcome-chapter-card__skills">
                        <span class="welcome-skill-tag">Data Analysis</span>
                        <span class="welcome-skill-tag">Pattern Recognition</span>
                    </div>
                </div>

                <div class="welcome-chapter-card">
                    <div class="welcome-chapter-card__header">
                        <span class="welcome-chapter-card__number">06</span>
                        <span class="welcome-chapter-card__type">Interactive</span>
                    </div>
                    <h3 class="welcome-chapter-card__title">AI Assistant Chat System</h3>
                    <p class="welcome-chapter-card__description">Create a conversational AI assistant that helps citizens understand complaint statuses and city services.</p>
                    <div class="welcome-chapter-card__skills">
                        <span class="welcome-skill-tag">Chat Interface</span>
                        <span class="welcome-skill-tag">Real-time Updates</span>
                    </div>
                </div>

                <div class="welcome-chapter-card">
                    <div class="welcome-chapter-card__header">
                        <span class="welcome-chapter-card__number">07</span>
                        <span class="welcome-chapter-card__type">Dashboard</span>
                    </div>
                    <h3 class="welcome-chapter-card__title">AI-Powered Analytics Dashboard</h3>
                    <p class="welcome-chapter-card__description">Build beautiful, interactive dashboards that surface AI-generated insights and actionable data visualizations.</p>
                    <div class="welcome-chapter-card__skills">
                        <span class="welcome-skill-tag">Data Visualization</span>
                        <span class="welcome-skill-tag">Charts & Graphs</span>
                    </div>
                </div>

                <div class="welcome-chapter-card">
                    <div class="welcome-chapter-card__header">
                        <span class="welcome-chapter-card__number">08</span>
                        <span class="welcome-chapter-card__type">Performance</span>
                    </div>
                    <h3 class="welcome-chapter-card__title">Optimization & Caching</h3>
                    <p class="welcome-chapter-card__description">Optimize AI API calls, implement intelligent caching strategies, and ensure your app scales efficiently.</p>
                    <div class="welcome-chapter-card__skills">
                        <span class="welcome-skill-tag">Performance</span>
                        <span class="welcome-skill-tag">Caching</span>
                    </div>
                </div>

                <!-- Bonus Chapters -->
                <div class="welcome-chapter-card welcome-chapter-card--bonus">
                    <div class="welcome-chapter-card__header">
                        <span class="welcome-chapter-card__number">09</span>
                        <span class="welcome-chapter-card__type">Bonus</span>
                    </div>
                    <h3 class="welcome-chapter-card__title">Advanced AI Workflows</h3>
                    <p class="welcome-chapter-card__description">Implement complex AI workflows with multi-step processing, automated decision trees, and intelligent routing.</p>
                    <div class="welcome-chapter-card__skills">
                        <span class="welcome-skill-tag">Workflow Automation</span>
                        <span class="welcome-skill-tag">Decision Trees</span>
                    </div>
                </div>

                <div class="welcome-chapter-card welcome-chapter-card--bonus">
                    <div class="welcome-chapter-card__header">
                        <span class="welcome-chapter-card__number">10</span>
                        <span class="welcome-chapter-card__type">Bonus</span>
                    </div>
                    <h3 class="welcome-chapter-card__title">Production Deployment</h3>
                    <p class="welcome-chapter-card__description">Deploy your AI-powered Laravel application to production with proper security, monitoring, and scalability considerations.</p>
                    <div class="welcome-chapter-card__skills">
                        <span class="welcome-skill-tag">DevOps</span>
                        <span class="welcome-skill-tag">Production Security</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA Section -->
    <section class="welcome-final-cta">
        <div class="welcome-final-cta__container">
            <h2 class="welcome-final-cta__title">Ready to Become AI Capable?</h2>
            <p class="welcome-final-cta__subtitle">Join thousands of Laravel developers who've transformed their careers with AI skills</p>
            
            <div class="welcome-final-cta__buttons">
                @auth
                    <a href="{{ url('/dashboard') }}" class="welcome-btn welcome-btn--primary">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Start Learning Now
                    </a>
                @else
                    <a href="{{ route('register') }}" class="welcome-btn welcome-btn--primary">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M13 10V3L4 14h7v7l9-11h-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        Start Learning Now
                    </a>
                @endauth
                
                <a href="https://github.com/bmadigan/LaraCity" target="_blank" class="welcome-btn welcome-btn--outline" rel="noopener noreferrer">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 0C5.372 0 0 5.501 0 12.304c0 5.435 3.438 10.044 8.207 11.674.6.112.818-.268.818-.592 0-.292-.01-1.066-.016-2.092-3.338.745-4.043-1.652-4.043-1.652-.545-1.419-1.332-1.796-1.332-1.796-1.09-.764.083-.749.083-.749 1.204.087 1.837 1.269 1.837 1.269 1.07 1.882 2.809 1.338 3.492 1.024.11-.797.42-1.338.763-1.645-2.664-.31-5.466-1.367-5.466-6.081 0-1.343.468-2.442 1.235-3.302-.124-.31-.535-1.562.117-3.257 0 0 1.008-.331 3.3 1.261A11.241 11.241 0 0 1 12 5.95c1.02.005 2.047.141 3.006.415 2.29-1.592 3.297-1.261 3.297-1.261.653 1.695.242 2.947.118 3.257.768.86 1.232 1.959 1.232 3.302 0 4.724-2.807 5.767-5.48 6.071.431.38.814 1.132.814 2.281 0 1.649-.015 2.976-.015 3.377 0 .328.216.712.826.591C20.565 22.344 24 17.737 24 12.304 24 5.501 18.628 0 12 0Z" fill="currentColor"/>
                    </svg>
                    Explore Repository
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="welcome-footer">
        <div class="welcome-footer__container">
            <div class="welcome-footer__content">
                <div class="welcome-footer__brand">
                    <div class="welcome-footer__logo">LC</div>
                    <div>
                        <h3 class="welcome-footer__title">{{ config('app.name', 'LaraCity') }}</h3>
                        <p class="welcome-footer__description">Transforming Laravel developers into AI-capable professionals through practical, production-ready tutorials.</p>
                    </div>
                </div>
                
                <div class="welcome-footer__links">
                    <div class="welcome-footer__link-group">
                        <h4 class="welcome-footer__link-title">Learning</h4>
                        <a href="#chapters" class="welcome-footer__link">All Chapters</a>
                        <a href="#" class="welcome-footer__link">Prerequisites</a>
                        <a href="https://madigan.dev" target="_blank" class="welcome-footer__link" rel="noopener noreferrer">Madigan.dev</a>
                    </div>
                    <div class="welcome-footer__link-group">
                        <h4 class="welcome-footer__link-title">Resources</h4>
                        <a href="https://github.com/bmadigan/LaraCity" target="_blank" class="welcome-footer__link" rel="noopener noreferrer">GitHub Repository</a>
                        <a href="https://laravel.com/docs" target="_blank" class="welcome-footer__link" rel="noopener noreferrer">Laravel Documentation</a>
                        <a href="https://python.langchain.com/docs/" target="_blank" class="welcome-footer__link" rel="noopener noreferrer">LangChain Documentation</a>
                    </div>
                    <div class="welcome-footer__link-group">
                        <h4 class="welcome-footer__link-title">Legal</h4>
                        <a href="https://laravel.com" target="_blank" class="welcome-footer__link" rel="noopener noreferrer">Laravel License</a>
                        <a href="https://python.langchain.com/docs/" target="_blank" class="welcome-footer__link" rel="noopener noreferrer">LangChain Terms</a>
                    </div>
                </div>
            </div>
            
            <div class="welcome-footer__bottom">
                <p class="welcome-footer__copyright">
                    © {{ date('Y') }} {{ config('app.name', 'LaraCity AI') }}. Built with Laravel & AI for the developer community. 
                    <br>Created by <a href="https://madigan.dev" target="_blank" class="welcome-footer__link" rel="noopener noreferrer">Madigan.dev</a>
                </p>
            </div>
        </div>
    </footer>

    <!-- Enhanced JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Keyboard navigation support
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Tab') {
                    document.body.classList.add('using-keyboard');
                }
            });

            document.addEventListener('mousedown', function() {
                document.body.classList.remove('using-keyboard');
            });

            // Enhanced demo button with better UX
            document.querySelectorAll('.welcome-btn--outline').forEach(btn => {
                if (btn.textContent.includes('Demo')) {
                    btn.addEventListener('click', function() {
                        // Create a more elegant notification
                        const notification = document.createElement('div');
                        notification.style.cssText = `
                            position: fixed;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%);
                            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
                            color: white;
                            padding: 2rem 3rem;
                            border-radius: 1rem;
                            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                            text-align: center;
                            z-index: 9999;
                            max-width: 90vw;
                            backdrop-filter: blur(10px);
                        `;
                        notification.innerHTML = `
                            <div style="font-size: 3rem; margin-bottom: 1rem;">🚀</div>
                            <h3 style="margin: 0 0 1rem 0; font-size: 1.5rem;">Demo Coming Soon!</h3>
                            <p style="margin: 0; opacity: 0.9;">We're building an interactive demo. Sign up to be notified when it's ready!</p>
                        `;
                        
                        document.body.appendChild(notification);
                        
                        // Remove after 3 seconds
                        setTimeout(() => {
                            notification.style.opacity = '0';
                            notification.style.transform = 'translate(-50%, -50%) translateY(20px)';
                            setTimeout(() => {
                                if (notification.parentNode) {
                                    notification.parentNode.removeChild(notification);
                                }
                            }, 300);
                        }, 3000);
                    });
                }
            });
        });
    </script>
</body>
</html>