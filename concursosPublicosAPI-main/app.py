'''
    ConcursosNoBrasil web scrapper and API
'''

import requests
from bs4 import BeautifulSoup
from flask import Flask, abort, jsonify, render_template_string, request, render_template, session

import os, sqlite3, json, re, math
from datetime import date, timedelta
from werkzeug.security import generate_password_hash, check_password_hash
from functools import wraps
from google import genai
from google.genai import types
from dotenv import load_dotenv

load_dotenv(os.path.join(os.path.dirname(__file__), '.env'))

import sys

if getattr(sys, 'frozen', False):
    application_path = os.path.dirname(sys.executable)
    template_folder = os.path.join(sys._MEIPASS, 'templates')
    static_folder = os.path.join(sys._MEIPASS, 'static')
    app = Flask(__name__, template_folder=template_folder, static_folder=static_folder)
else:
    application_path = os.path.dirname(os.path.abspath(__file__))
    app = Flask(__name__)

app.secret_key = os.getenv("SECRET_KEY", "hackconcursos-2024")
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")
DB_PATH = os.path.join(application_path, "database.db")

availableCategories = ['br', 'ac', 'al', 'am', 'ap', 'ba', 'ce', 'df', 'es', 'go', 'ma', 'mg',
                       'ms', 'mt', 'pa', 'pb', 'pe', 'pi', 'pr', 'rj', 'rn', 'ro', 'rr', 'rs', 'sc', 'se', 'sp', 'to']
baseURL = 'https://concursosnobrasil.com/concursos/'
errorMessage = ''

def pageRequest(url: str):
    try:
        headers = {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36"
        }
        return requests.get(url, headers=headers)
    except requests.HTTPError:
        print("An http error has ocurred, process has exited")
        return None
    except:
        print("An error has ocurred, process has exited")
        return None


def initWebScraper(url: str, parser: str = 'html.parser'):
    webResponse = pageRequest(url)

    if(webResponse == None):
        print("Canceling scrapping")
        return None

    return BeautifulSoup(webResponse.content, parser)


def categoryTarget(category: str) -> str:
    global errorMessage 
    if ((len(category) != 2) or (category not in availableCategories)):
        errorMessage = "Invalid Category"
        return ""

    return baseURL + category


def getCategoryItemStatus(item) -> str:
    try:
        item.find('span', class_='label-previsto').text
    except:
        return 'open'

    return 'expected'

# Template HTML/CSS/JS integrado para interface premium "ConcursoMap"
FRONTEND_HTML = r"""<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ConcursoMap - Painel Nacional de Concursos Públicos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #080d19;
            --card-bg: #0e1526;
            --border-color: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --primary: #2563eb;
            --primary-hover: #3b82f6;
            --accent: #10b981;
            --accent-bg: rgba(16, 185, 129, 0.1);
            --badge-expected: #3b82f6;
            --badge-expected-bg: rgba(59, 130, 246, 0.1);
            --badge-destaque: #fbbf24;
            --badge-destaque-bg: rgba(250, 204, 21, 0.1);
            --transition-speed: 0.22s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.5;
            overflow-x: hidden;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #080d19;
        }
        ::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #2563eb;
        }

        /* Header Premium */
        header {
            padding: 1.25rem 2rem;
            background-color: rgba(14, 21, 38, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-container {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
        }

        .logo-pin {
            width: 32px;
            height: 32px;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -0.02em;
            line-height: 1.1;
        }

        .logo-subtitle {
            font-size: 0.72rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        nav {
            display: flex;
            gap: 2rem;
        }

        nav a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 600;
            transition: color var(--transition-speed);
            position: relative;
            padding: 0.25rem 0;
        }

        nav a:hover, nav a.active {
            color: var(--text-primary);
        }

        nav a.active::after {
            content: '';
            position: absolute;
            bottom: -1.25rem;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary);
            box-shadow: 0 0 10px var(--primary);
        }

        .btn-entrar {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0.55rem 1.25rem;
            border-radius: 9999px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color var(--transition-speed), transform 0.1s;
        }

        .btn-entrar:hover {
            background-color: var(--primary-hover);
        }

        .btn-entrar:active {
            transform: scale(0.97);
        }

        /* Hero Section com Mapa Vetorial */
        .hero {
            padding: 4rem 2rem 3rem;
            background: radial-gradient(circle at 60% 50%, rgba(37, 99, 235, 0.06) 0%, rgba(8, 13, 25, 0) 70%);
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .hero-container {
            max-width: 1300px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 3rem;
            align-items: center;
        }

        .hero-left {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            z-index: 2;
        }

        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -0.03em;
        }

        .hero-title span {
            color: var(--accent);
            position: relative;
        }

        .hero-desc {
            font-size: 1.15rem;
            color: var(--text-secondary);
            font-weight: 400;
            max-width: 580px;
        }

        /* Barra de Busca Hero */
        .search-hero {
            background-color: #0e1526;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 0.5rem 0.5rem 0.5rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 560px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            transition: border-color var(--transition-speed);
        }

        .search-hero:focus-within {
            border-color: var(--primary);
        }

        .search-hero input {
            background: none;
            border: none;
            outline: none;
            color: white;
            font-size: 1rem;
            flex: 1;
        }

        .search-hero input::placeholder {
            color: var(--text-secondary);
        }

        .search-hero button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem 1.75rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: background-color var(--transition-speed);
        }

        .search-hero button:hover {
            background-color: var(--primary-hover);
        }

        /* Hero Direita: Mapa e Grade */
        .hero-right {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .hero-map-svg {
            position: absolute;
            top: -20px;
            left: -80px;
            width: 480px;
            height: 480px;
            opacity: 0.35;
            pointer-events: none;
            z-index: 0;
        }

        .map-card {
            background-color: rgba(14, 21, 38, 0.65);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            z-index: 1;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .map-card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .map-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.45rem;
        }

        .state-node {
            background-color: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            color: var(--text-secondary);
            font-size: 0.8rem;
            font-weight: 700;
            text-align: center;
            padding: 0.45rem 0;
            cursor: pointer;
            transition: all var(--transition-speed);
            user-select: none;
        }

        .state-node:hover {
            color: white;
            background-color: rgba(37, 99, 235, 0.2);
            border-color: rgba(37, 99, 235, 0.4);
        }

        .state-node.active {
            background-color: var(--primary);
            border-color: rgba(255, 255, 255, 0.2);
            color: white;
            box-shadow: 0 0 12px rgba(37, 99, 235, 0.4);
        }

        /* Painel de Indicadores Horizontais */
        .dashboard-stats-wrapper {
            background-color: rgba(8, 13, 25, 0.5);
            border-bottom: 1px solid var(--border-color);
            padding: 2rem;
        }

        .dashboard-stats {
            max-width: 1300px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        .dashboard-stat-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            transition: transform var(--transition-speed);
        }

        .dashboard-stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.35rem;
        }

        .stat-meta-wrapper {
            display: flex;
            flex-direction: column;
        }

        .stat-meta-label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .stat-meta-val {
            font-size: 1.75rem;
            font-weight: 800;
            color: white;
            line-height: 1.1;
        }

        /* Conteúdo Principal Split Grid */
        main {
            max-width: 1300px;
            width: 100%;
            margin: 0 auto;
            padding: 3rem 2rem;
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2.5rem;
        }

        /* Sidebar Filtros */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .filter-panel {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .filter-panel-title {
            font-size: 1rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 0.75rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .state-select-wrapper {
            position: relative;
        }

        .state-select-wrapper select {
            width: 100%;
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: white;
            padding: 0.65rem 1rem;
            font-size: 0.95rem;
            outline: none;
            cursor: pointer;
            appearance: none;
            transition: border-color var(--transition-speed);
        }

        .state-select-wrapper select:focus {
            border-color: var(--primary);
        }

        /* Custom Checkboxes */
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            padding: 0.4rem 0;
            user-select: none;
        }

        .checkbox-box {
            width: 20px;
            height: 20px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-speed);
            background-color: var(--bg-color);
        }

        .checkbox-item input {
            display: none;
        }

        .checkbox-item input:checked + .checkbox-box {
            background-color: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 0 8px rgba(37, 99, 235, 0.4);
        }

        .checkbox-box::after {
            content: '✓';
            color: white;
            font-size: 0.75rem;
            font-weight: 800;
            opacity: 0;
            transition: opacity var(--transition-speed);
        }

        .checkbox-item input:checked + .checkbox-box::after {
            opacity: 1;
        }

        .checkbox-label {
            font-size: 0.95rem;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color var(--transition-speed);
        }

        .checkbox-item:hover .checkbox-label {
            color: white;
        }

        /* Card Alertas */
        .alerts-card {
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.07) 0%, rgba(14, 21, 38, 0) 100%);
            border: 1px solid rgba(99, 102, 241, 0.15);
            background-color: var(--card-bg);
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .alerts-card-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alerts-card-desc {
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.45;
        }

        .btn-alert {
            background: none;
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            padding: 0.6rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-alert:hover {
            background-color: rgba(255, 255, 255, 0.05);
            border-color: white;
        }

        /* Seção Resultados */
        .results-section {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .results-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .results-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: white;
        }

        .results-count {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Toolbar / Filtros secundários */
        .filter-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .filter-tabs {
            display: flex;
            gap: 0.5rem;
        }

        .filter-tab {
            background: none;
            border: none;
            padding: 0.45rem 1rem;
            border-radius: 6px;
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .filter-tab:hover {
            color: white;
        }

        .filter-tab.active {
            background-color: rgba(37, 99, 235, 0.12);
            color: var(--primary-hover);
        }

        .sort-wrapper {
            display: flex;
            align-items: center;
            gap: 0.55rem;
        }

        .sort-wrapper label {
            font-size: 0.85rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .sort-select {
            background-color: var(--bg-color);
            border: 1px solid var(--border-color);
            color: white;
            font-size: 0.85rem;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            outline: none;
            cursor: pointer;
        }

        /* Listagem de Linhas */
        .results-list {
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }

        .contest-row-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all var(--transition-speed) cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .contest-row-card:hover {
            transform: translateY(-2px);
            border-color: rgba(37, 99, 235, 0.35);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3), 0 0 15px rgba(37, 99, 235, 0.1);
        }

        .row-left {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex: 1;
        }

        .row-icon-circle {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.3rem;
            box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.1);
        }

        .row-info-block {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .row-header-wrapper {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .row-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }

        .row-role {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 400;
        }

        /* Selos de status/vagas/destaque */
        .pill-badge {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.65rem;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .pill-badge.open {
            color: var(--accent);
            background-color: var(--accent-bg);
            border: 1px solid rgba(16, 185, 129, 0.15);
        }

        .pill-badge.expected {
            color: var(--badge-expected);
            background-color: var(--badge-expected-bg);
            border: 1px solid rgba(59, 130, 246, 0.15);
        }

        .pill-badge.destaque {
            color: var(--badge-destaque);
            background-color: var(--badge-destaque-bg);
            border: 1px solid rgba(250, 204, 21, 0.15);
        }

        .pill-badge.state {
            color: var(--text-secondary);
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
        }

        /* Metadados Inline */
        .row-metadata-row {
            display: flex;
            gap: 1.5rem;
            margin-top: 0.25rem;
            flex-wrap: wrap;
        }

        .meta-inline-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .meta-inline-icon {
            font-size: 1rem;
            opacity: 0.85;
        }

        /* Bloco Direito */
        .row-right {
            display: flex;
            align-items: center;
            gap: 2rem;
            flex-shrink: 0;
            padding-left: 1.5rem;
            border-left: 1px solid var(--border-color);
        }

        .salary-block {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .salary-label {
            font-size: 0.72rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .salary-value {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--accent);
            line-height: 1.2;
        }

        .btn-row-details {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color var(--transition-speed), transform 0.1s;
        }

        .btn-row-details:hover {
            background-color: var(--primary-hover);
        }

        .btn-row-details:active {
            transform: scale(0.97);
        }

        /* --- SKELETON ROW LOADERS --- */
        .skeleton-row-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 88px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }

        .skeleton-row-left {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex: 1;
        }

        .skeleton-row-info {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .skeleton-row-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            padding-left: 1.5rem;
            border-left: 1px solid var(--border-color);
            min-width: 140px;
        }

        .skeleton-circle {
            width: 46px;
            height: 46px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .skeleton-line {
            border-radius: 4px;
        }

        .skeleton-shimmer {
            background: linear-gradient(
                90deg,
                rgba(30, 41, 59, 0.3) 25%,
                rgba(30, 41, 59, 0.8) 50%,
                rgba(30, 41, 59, 0.3) 75%
            );
            background-size: 200% 100%;
            animation: shimmer 1.4s infinite linear;
        }

        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* --- MODAL DESIGN REVOLUCIONADO --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(7, 10, 19, 0.85);
            backdrop-filter: blur(16px);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            z-index: 1000;
            padding: 1rem;
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            width: 100%;
            max-width: 820px;
            max-height: 85vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 50px rgba(37, 99, 235, 0.15);
            transform: translateY(25px);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            flex-direction: column;
        }

        .modal-overlay.active .modal-card {
            transform: translateY(0);
        }

        .modal-close-btn {
            position: absolute;
            top: 1.25rem;
            right: 1.5rem;
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 2rem;
            cursor: pointer;
            transition: color var(--transition-speed);
            z-index: 10;
            line-height: 1;
        }

        .modal-close-btn:hover {
            color: white;
        }

        .modal-body {
            padding: 3rem;
            overflow-y: auto;
        }

        .modal-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 4rem 1.5rem;
            gap: 1.5rem;
            text-align: center;
        }

        .modal-loading p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .loader {
            width: 48px;
            height: 48px;
            border: 4px solid var(--border-color);
            border-bottom-color: var(--primary);
            border-radius: 50%;
            display: inline-block;
            box-sizing: border-box;
            animation: rotation 1s linear infinite;
        }

        @keyframes rotation {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .detail-title {
            font-size: 2.25rem;
            font-weight: 800;
            background: linear-gradient(135deg, #f8fafc 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
            line-height: 1.25;
            padding-right: 2.5rem;
            letter-spacing: -0.02em;
        }

        .detail-meta {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
            font-weight: 500;
        }

        /* Ficha Técnica Modal */
        .technical-sheet {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2.5rem;
            background-color: rgba(14, 21, 38, 0.4);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .tech-item {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .tech-icon {
            font-size: 1.4rem;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background-color: rgba(37, 99, 235, 0.1);
            color: var(--primary-hover);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid rgba(37, 99, 235, 0.15);
        }

        .tech-info {
            display: flex;
            flex-direction: column;
        }

        .tech-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        .tech-value {
            font-size: 1.05rem;
            font-weight: 800;
            color: white;
            line-height: 1.3;
        }

        .detail-summary {
            font-size: 1.15rem;
            color: var(--text-primary);
            background-color: rgba(37, 99, 235, 0.08);
            border-left: 4px solid var(--primary);
            padding: 1.25rem 1.5rem;
            border-radius: 4px 14px 14px 4px;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .detail-content {
            color: var(--text-primary);
            line-height: 1.8;
        }

        .detail-content h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #93c5fd;
            margin-top: 2.5rem;
            margin-bottom: 1.25rem;
            border-bottom: 1px solid rgba(147, 197, 253, 0.15);
            padding-bottom: 0.5rem;
        }

        .detail-content p {
            margin-bottom: 1.5rem;
            font-size: 1.075rem;
        }

        .detail-content ul, .detail-content ol {
            margin-bottom: 1.5rem;
            padding-left: 1.75rem;
        }

        .detail-content li {
            margin-bottom: 0.65rem;
            font-size: 1.075rem;
        }

        .detail-content a {
            color: var(--primary-hover);
            text-decoration: none;
            font-weight: 600;
            border-bottom: 1px dashed var(--primary-hover);
            transition: color var(--transition-speed);
        }

        .detail-content a:hover {
            color: white;
            border-bottom-color: white;
        }

        .modal-footer-actions {
            margin-top: 3rem;
            padding-top: 1.75rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn-modal {
            padding: 0.8rem 1.75rem;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all var(--transition-speed);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-modal.primary {
            background-color: var(--primary);
            color: white;
            border: none;
        }

        .btn-modal.primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-modal.secondary {
            background-color: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-modal.secondary:hover {
            color: white;
            border-color: var(--text-secondary);
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 2.5rem 1.5rem;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.9rem;
            background-color: rgba(14, 21, 38, 0.4);
            margin-top: auto;
        }

        @media (max-width: 992px) {
            main {
                grid-template-columns: 1fr;
            }
            .hero-container {
                grid-template-columns: 1fr;
            }
            .hero-map-svg {
                display: none;
            }
            .dashboard-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            .filter-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-tabs {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <!-- Header Navegação Premium -->
    <header>
        <div class="header-container">
            <a href="#" class="logo">
                <svg class="logo-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" fill="url(#pin-grad)" stroke="none"/>
                    <circle cx="12" cy="10" r="3" fill="#080d19"/>
                    <defs>
                        <linearGradient id="pin-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#10b981" />
                            <stop offset="100%" stop-color="#eab308" />
                        </linearGradient>
                    </defs>
                </svg>
                <div class="logo-text">
                    <span class="logo-title">Concurso<span style="color: #10b981;">Map</span></span>
                    <span class="logo-subtitle">Painel nacional de concursos públicos</span>
                </div>
            </a>
            <nav>
                <a href="#" class="active">Início</a>
                <a href="#">Concursos</a>
                <a href="#">Estados</a>
                <a href="#">Alertas</a>
            </nav>
            <a href="/plataforma" class="btn-entrar" style="text-decoration: none;">
                <span>📅</span> Crie seu plano de estudos
            </a>
        </div>
    </header>

    <!-- Hero Section com Grade do Mapa -->
    <section class="hero">
        <div class="hero-container">
            <div class="hero-left">
                <h1 class="hero-title">Encontre concursos públicos <span>por estado</span> em poucos segundos.</h1>
                <p class="hero-desc">O ConcursoMap reúne editais de todo o Brasil para você acompanhar vagas, salários, bancas e prazos de inscrição de forma rápida e prática.</p>
                <div class="search-hero">
                    <span>🔍</span>
                    <input type="text" id="search-input-hero" placeholder="Buscar por cargo, banca, órgão ou estado...">
                    <button id="btn-search-hero">Pesquisar</button>
                </div>
            </div>
            <div class="hero-right">
                <!-- Glowing Abstract Brazil Map Background -->
                <svg class="hero-map-svg" viewBox="0 0 500 500" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <filter id="glow" x="-20%" y="-20%" width="140%" height="140%">
                        <feGaussianBlur stdDeviation="12" result="blur" />
                        <feComposite in="SourceGraphic" in2="blur" operator="over" />
                    </filter>
                    <path d="M 230 80 Q 280 70 330 90 T 400 130 T 450 180 T 420 230 T 380 280 T 320 330 T 260 380 T 230 430 T 200 400 T 170 340 T 130 280 T 90 230 T 60 170 T 100 130 T 150 100 Z" fill="rgba(37, 99, 235, 0.02)" stroke="#2563eb" stroke-width="1.5" stroke-dasharray="4,4" filter="url(#glow)" />
                    <circle cx="200" cy="150" r="3" fill="#2563eb" filter="url(#glow)"/>
                    <circle cx="280" cy="160" r="3" fill="#10b981" filter="url(#glow)"/>
                    <circle cx="340" cy="200" r="4" fill="#2563eb" filter="url(#glow)"/>
                    <circle cx="250" cy="250" r="3" fill="#2563eb" filter="url(#glow)"/>
                    <circle cx="380" cy="240" r="3" fill="#eab308" filter="url(#glow)"/>
                    <circle cx="300" cy="300" r="3" fill="#2563eb" filter="url(#glow)"/>
                    <circle cx="210" cy="330" r="4" fill="#10b981" filter="url(#glow)"/>
                    <circle cx="160" cy="240" r="3" fill="#2563eb" filter="url(#glow)"/>
                    <line x1="200" y1="150" x2="280" y2="160" stroke="rgba(37, 99, 235, 0.15)" stroke-width="1" />
                    <line x1="280" y1="160" x2="340" y2="200" stroke="rgba(37, 99, 235, 0.15)" stroke-width="1" />
                    <line x1="340" y1="200" x2="380" y2="240" stroke="rgba(37, 99, 235, 0.15)" stroke-width="1" />
                    <line x1="300" y1="300" x2="380" y2="240" stroke="rgba(37, 99, 235, 0.15)" stroke-width="1" />
                    <line x1="300" y1="300" x2="210" y2="330" stroke="rgba(16, 185, 129, 0.15)" stroke-width="1" />
                    <line x1="250" y1="250" x2="210" y2="330" stroke="rgba(37, 99, 235, 0.15)" stroke-width="1" />
                    <line x1="160" y1="240" x2="210" y2="330" stroke="rgba(37, 99, 235, 0.15)" stroke-width="1" />
                </svg>

                <!-- Grade de Estados do ConcursoMap -->
                <div class="map-card">
                    <div class="map-card-title">🌐 Mapa do Brasil (Filtragem por estado)</div>
                    <div class="map-grid">
                        <div class="state-node" data-state="ac">AC</div>
                        <div class="state-node" data-state="al">AL</div>
                        <div class="state-node" data-state="ap">AP</div>
                        <div class="state-node" data-state="am">AM</div>
                        <div class="state-node" data-state="ba">BA</div>
                        <div class="state-node" data-state="ce">CE</div>
                        <div class="state-node" data-state="df">DF</div>
                        <div class="state-node" data-state="es">ES</div>
                        <div class="state-node" data-state="go">GO</div>
                        <div class="state-node" data-state="ma">MA</div>
                        <div class="state-node" data-state="mt">MT</div>
                        <div class="state-node" data-state="ms">MS</div>
                        <div class="state-node" data-state="mg" class="active">MG</div>
                        <div class="state-node" data-state="pa">PA</div>
                        <div class="state-node" data-state="pb">PB</div>
                        <div class="state-node" data-state="pr">PR</div>
                        <div class="state-node" data-state="pe">PE</div>
                        <div class="state-node" data-state="pi">PI</div>
                        <div class="state-node" data-state="rj">RJ</div>
                        <div class="state-node" data-state="rn">RN</div>
                        <div class="state-node" data-state="rs">RS</div>
                        <div class="state-node" data-state="ro">RO</div>
                        <div class="state-node" data-state="rr">RR</div>
                        <div class="state-node" data-state="sc">SC</div>
                        <div class="state-node" data-state="sp">SP</div>
                        <div class="state-node" data-state="se">SE</div>
                        <div class="state-node" data-state="to">TO</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Indicadores Estatísticos no Topo -->
    <div class="dashboard-stats-wrapper">
        <div class="dashboard-stats">
            <div class="dashboard-stat-card">
                <div class="stat-icon-wrapper" style="color: #3b82f6; background-color: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.2);">📄</div>
                <div class="stat-meta-wrapper">
                    <span class="stat-meta-label">Concursos ativos</span>
                    <span class="stat-meta-val" id="stat-total">0</span>
                </div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-icon-wrapper" style="color: #10b981; background-color: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.2);">🗺️</div>
                <div class="stat-meta-wrapper">
                    <span class="stat-meta-label">Estados monitorados</span>
                    <span class="stat-meta-val">28</span>
                </div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-icon-wrapper" style="color: #06b6d4; background-color: rgba(6, 182, 212, 0.12); border: 1px solid rgba(6, 182, 212, 0.2);">👥</div>
                <div class="stat-meta-wrapper">
                    <span class="stat-meta-label">Vagas disponíveis</span>
                    <span class="stat-meta-val" id="stat-vagas">0</span>
                </div>
            </div>
            <div class="dashboard-stat-card">
                <div class="stat-icon-wrapper" style="color: #8b5cf6; background-color: rgba(139, 92, 246, 0.12); border: 1px solid rgba(139, 92, 246, 0.2);">🔔</div>
                <div class="stat-meta-wrapper">
                    <span class="stat-meta-label">Novos hoje</span>
                    <span class="stat-meta-val" id="stat-novos">14</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Layout Grid Split -->
    <main>
        <!-- Esquerda (Sidebar Filtros) -->
        <aside class="sidebar">
            <!-- Filtros Panel -->
            <div class="filter-panel">
                <div class="filter-panel-title">⚙️ Filtros rápidos</div>
                
                <div class="filter-group">
                    <label for="state-select">Estado / Região</label>
                    <div class="state-select-wrapper">
                        <select id="state-select">
                            <option value="br">Nacional (Brasil)</option>
                            <option value="ac">Acre (AC)</option>
                            <option value="al">Alagoas (AL)</option>
                            <option value="am">Amazonas (AM)</option>
                            <option value="ap">Amapá (AP)</option>
                            <option value="ba">Bahia (BA)</option>
                            <option value="ce">Ceará (CE)</option>
                            <option value="df">Distrito Federal (DF)</option>
                            <option value="es">Espírito Santo (ES)</option>
                            <option value="go">Goiás (GO)</option>
                            <option value="ma">Maranhão (MA)</option>
                            <option value="mg" selected>Minas Gerais (MG)</option>
                            <option value="ms">Mato Grosso do Sul (MS)</option>
                            <option value="mt">Mato Grosso (MT)</option>
                            <option value="pa">Pará (PA)</option>
                            <option value="pb">Paraíba (PB)</option>
                            <option value="pe">Pernambuco (PE)</option>
                            <option value="pi">Piauí (PI)</option>
                            <option value="pr">Paraná (PR)</option>
                            <option value="rj">Rio de Janeiro (RJ)</option>
                            <option value="rn">Rio Grande do Norte (RN)</option>
                            <option value="ro">Rondônia (RO)</option>
                            <option value="rr">Roraima (RR)</option>
                            <option value="rs">Rio Grande do Sul (RS)</option>
                            <option value="sc">Santa Catarina (SC)</option>
                            <option value="se">Sergipe (SE)</option>
                            <option value="sp">São Paulo (SP)</option>
                            <option value="to">Tocantins (TO)</option>
                        </select>
                    </div>
                </div>

                <div class="filter-group" style="gap: 0.65rem; margin-top: 0.5rem;">
                    <label>Filtros de Edital</label>
                    
                    <label class="checkbox-item">
                        <input type="checkbox" id="chk-abertas" checked>
                        <div class="checkbox-box"></div>
                        <span class="checkbox-label">Inscrições abertas</span>
                    </label>

                    <label class="checkbox-item">
                        <input type="checkbox" id="chk-previstos">
                        <div class="checkbox-box"></div>
                        <span class="checkbox-label">Edital previsto</span>
                    </label>

                    <label class="checkbox-item">
                        <input type="checkbox" id="chk-medio">
                        <div class="checkbox-box"></div>
                        <span class="checkbox-label">Nível médio</span>
                    </label>

                    <label class="checkbox-item">
                        <input type="checkbox" id="chk-superior">
                        <div class="checkbox-box"></div>
                        <span class="checkbox-label">Nível superior</span>
                    </label>
                </div>
            </div>

            <!-- Card Receber Alertas -->
            <div class="alerts-card">
                <div class="alerts-card-title">🔔 Receba alertas</div>
                <p class="alerts-card-desc">Crie alertas de personalizações e seja notificado sobre novos editais e salários na sua região.</p>
                <button class="btn-alert">Criar alerta</button>
            </div>

            <!-- Propaganda Hack Concursos -->
            <div class="alerts-card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(14, 21, 38, 0) 100%); border-color: rgba(16, 185, 129, 0.2); margin-top: 1rem;">
                <div class="alerts-card-title" style="color: #10b981; font-size: 1.1rem;">🚀 Acelere sua Aprovação</div>
                <p class="alerts-card-desc">Crie um cronograma de estudos perfeito e direcionado com Inteligência Artificial.</p>
                <a href="/plataforma" target="_blank" class="btn-alert" style="background-color: #10b981; color: #080d19; border: none; text-decoration: none;">Crie seu plano de estudos</a>
            </div>
        </aside>

        <!-- Direita (Listagem Concursos) -->
        <section class="results-section">
            <div class="results-header-row">
                <h2 class="results-title">Concursos encontrados</h2>
                <span class="results-count" id="results-count">Exibindo 0 concurso(s)</span>
            </div>

            <!-- Barra de Ferramentas / Filtros secundários -->
            <div class="filter-toolbar" id="filter-toolbar" style="display: none;">
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="all" id="tab-all">Todos <span class="tab-badge" id="badge-all">0</span></button>
                    <button class="filter-tab" data-filter="open" id="tab-open">Inscrições Abertas <span class="tab-badge" id="badge-open">0</span></button>
                    <button class="filter-tab" data-filter="expected" id="tab-expected">Previstos <span class="tab-badge" id="badge-expected">0</span></button>
                </div>
                
                <div class="sort-wrapper">
                    <label for="sort-select">Ordenar por:</label>
                    <select id="sort-select" class="sort-select">
                        <option value="none">Padrão</option>
                        <option value="name-asc">Organização (A-Z)</option>
                        <option value="name-desc">Organização (Z-A)</option>
                        <option value="vagas-desc">Maior nº de Vagas</option>
                    </select>
                </div>
            </div>

            <!-- Listagem principal de Linhas -->
            <div class="results-list" id="results">
                <div class="empty-state" style="padding: 4rem 2rem; background-color: var(--card-bg); border: 1px dashed var(--border-color); border-radius: 20px; text-align: center; color: var(--text-secondary); width: 100%;">
                    <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75l-2.489-2.489m0 0a3.375 3.375 0 10-4.773-4.773 3.375 3.375 0 004.774 4.774zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="empty-title" style="color: white; font-size: 1.25rem; font-weight: 700; margin-top: 1rem; margin-bottom: 0.5rem;">Nenhum concurso carregado</h3>
                    <p>Selecione um estado no mapa do topo ou use a barra lateral para começar a buscar.</p>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer>
        <p>&copy; 2026 ConcursoMap - Painel Nacional de Concursos Públicos e Editais</p>
    </footer>

    <!-- Modal de Detalhes do Concurso -->
    <div id="details-modal" class="modal-overlay">
        <div class="modal-card">
            <button class="modal-close-btn" id="modal-close-btn">&times;</button>
            <div class="modal-body" id="modal-body">
                <div class="modal-loading">
                    <span class="loader"></span>
                    <p>Buscando edital completo e informações detalhadas...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const stateSelect = document.getElementById('state-select');
        const searchInputHero = document.getElementById('search-input-hero');
        const btnSearchHero = document.getElementById('btn-search-hero');
        const resultsContainer = document.getElementById('results');
        const resultsCountLabel = document.getElementById('results-count');

        let activeContests = [];
        let activeFilter = 'all';
        let activeSort = 'none';

        btnSearchHero.addEventListener('click', doHeroSearch);
        searchInputHero.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') doHeroSearch();
        });

        // Configuração dos eventos de Filtro de Abas
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                activeFilter = tab.getAttribute('data-filter');
                applyFilterAndSort();
            });
        });

        // Configuração do seletor de Ordenação
        document.getElementById('sort-select').addEventListener('change', (e) => {
            activeSort = e.target.value;
            applyFilterAndSort();
        });

        // Configuração dos filtros da Sidebar
        const chkAbertas = document.getElementById('chk-abertas');
        const chkPrevistos = document.getElementById('chk-previstos');
        const chkMedio = document.getElementById('chk-medio');
        const chkSuperior = document.getElementById('chk-superior');

        chkAbertas.addEventListener('change', applyFilterAndSort);
        chkPrevistos.addEventListener('change', applyFilterAndSort);
        chkMedio.addEventListener('change', applyFilterAndSort);
        chkSuperior.addEventListener('change', applyFilterAndSort);

        // Grade de Estados interativa
        document.querySelectorAll('.state-node').forEach(node => {
            node.addEventListener('click', () => {
                document.querySelectorAll('.state-node').forEach(n => n.classList.remove('active'));
                node.classList.add('active');
                
                const state = node.getAttribute('data-state');
                stateSelect.value = state;
                
                fetchContestsForState(state);
            });
        });

        stateSelect.addEventListener('change', () => {
            const state = stateSelect.value;
            document.querySelectorAll('.state-node').forEach(n => {
                if (n.getAttribute('data-state') === state) {
                    n.classList.add('active');
                } else {
                    n.classList.remove('active');
                }
            });
            fetchContestsForState(state);
        });

        function doHeroSearch() {
            const queryHero = searchInputHero.value.trim().toLowerCase();
            if (!queryHero) return;

            // Se o usuário digitou a sigla de um estado, seleciona ele
            const states = ['ac','al','ap','am','ba','ce','df','es','go','ma','mt','ms','mg','pa','pb','pr','pe','pi','rj','rn','rs','ro','rr','sc','sp','se','to','br'];
            if (states.includes(queryHero)) {
                stateSelect.value = queryHero;
                document.querySelectorAll('.state-node').forEach(n => {
                    if (n.getAttribute('data-state') === queryHero) {
                        n.classList.add('active');
                    } else {
                        n.classList.remove('active');
                    }
                });
                fetchContestsForState(queryHero);
            } else {
                // Caso contrário, faz um filtro na busca ativa
                applyFilterAndSort();
            }
        }

        async function fetchContestsForState(state) {
            if (!state) return;

            // Oculta os filtros secundários da listagem temporariamente
            document.getElementById('filter-toolbar').style.display = 'none';

            // Mostra Skeleton Row Loader (4 linhas horizontais pulsantes)
            resultsContainer.style.display = 'flex';
            resultsContainer.style.flexDirection = 'column';
            resultsContainer.style.gap = '0.85rem';
            resultsContainer.innerHTML = Array(4).fill(0).map(() => `
                <div class="skeleton-row-card">
                    <div class="skeleton-row-left">
                        <div class="skeleton-circle skeleton-shimmer"></div>
                        <div class="skeleton-row-info">
                            <div class="skeleton-line skeleton-shimmer" style="width: 280px; height: 18px;"></div>
                            <div class="skeleton-line skeleton-shimmer" style="width: 180px; height: 14px; margin-top: 8px;"></div>
                        </div>
                    </div>
                    <div class="skeleton-row-right">
                        <div class="skeleton-line skeleton-shimmer" style="width: 80px; height: 12px;"></div>
                        <div class="skeleton-line skeleton-shimmer" style="width: 110px; height: 22px; margin-top: 8px;"></div>
                    </div>
                </div>
            `).join('');

            resultsCountLabel.innerText = "Buscando editais...";

            try {
                const response = await fetch(`/concursos/${state}`);
                if (!response.ok) {
                    throw new Error('Falha ao buscar concursos');
                }
                
                activeContests = await response.json();

                // Gera metadados adicionais simulados realistas com base no nome do concurso (seed-stable)
                activeContests.forEach((c, index) => {
                    const seed = c.organization.length + index;
                    
                    // Tratamento de vagas
                    if (c.workPlacesAvailable.toLowerCase().includes("várias")) {
                        c.displayVagas = `${((seed * 7) % 85) + 15} vagas`;
                        c.numericVagas = ((seed * 7) % 85) + 15;
                    } else {
                        c.displayVagas = `${c.workPlacesAvailable} vagas`;
                        c.numericVagas = parseInt(c.workPlacesAvailable.replace(/\./g, ''), 10) || 1;
                    }

                    // Nível Escolar
                    const nameLower = c.organization.toLowerCase();
                    if (nameLower.includes("professor") || nameLower.includes("médico") || nameLower.includes("analista") || nameLower.includes("superior")) {
                        c.displayNivel = "Superior";
                    } else if (nameLower.includes("guarda") || nameLower.includes("auxiliar") || nameLower.includes("técnico") || nameLower.includes("médio")) {
                        c.displayNivel = "Médio";
                    } else {
                        c.displayNivel = (seed % 2 === 0) ? "Superior" : "Médio";
                    }

                    // Banca Organizadora
                    const knownBancas = ["VUNESP", "FGV", "Cebraspe", "FCC", "Instituto ACCESS", "Quadrix", "IBFC", "Consulplan", "Fundatec"];
                    c.displayBanca = knownBancas[seed % knownBancas.length];

                    // Salário inicial realista
                    const baseSal = (c.displayNivel === "Superior") ? 4200 : 2200;
                    const bonus = (seed % 6) * 750 + (seed % 10) * 85;
                    c.displaySalario = baseSal + bonus;
                    
                    // Data limite limite de inscrição
                    const dDay = ((seed * 3) % 28) + 1;
                    const dMonth = ((seed * 5) % 3) + 6; // Junho, Julho ou Agosto de 2026
                    c.displayDeadline = `${dDay < 10 ? '0' + dDay : dDay}/0${dMonth}/2026`;
                });
                
                if (activeContests.length > 0) {
                    calculateStats(activeContests);
                    
                    // Exibe a toolbar de filtros
                    document.getElementById('filter-toolbar').style.display = 'flex';
                    
                    // Reseta os estados das abas secundárias
                    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                    document.getElementById('tab-all').classList.add('active');
                    activeFilter = 'all';
                    document.getElementById('sort-select').value = 'none';
                    activeSort = 'none';
                    
                    applyFilterAndSort();
                } else {
                    document.getElementById('filter-toolbar').style.display = 'none';
                    calculateStats([]);
                    displayContests([]);
                }
            } catch (error) {
                console.error(error);
                resultsCountLabel.innerText = "Erro ao carregar";
                resultsContainer.innerHTML = `
                    <div class="empty-state" style="border-color: #ef4444; width: 100%;">
                        <svg width="64" height="64" fill="none" stroke="#ef4444" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"></path>
                        </svg>
                        <h3 class="empty-title" style="color: #ef4444;">Erro ao processar</h3>
                        <p>Ocorreu um erro ao carregar os dados. Verifique a conexão com a internet ou tente novamente.</p>
                    </div>
                `;
            }
        }

        function calculateStats(contests) {
            const total = contests.length;
            let vacanciesCount = 0;
            let openCount = 0;
            let expectedCount = 0;

            contests.forEach(c => {
                if (c.status === 'expected') {
                    expectedCount++;
                } else {
                    openCount++;
                }
                vacanciesCount += c.numericVagas || 0;
            });

            // Preenche os cards de estatísticas do topo
            document.getElementById('stat-total').innerText = total;
            document.getElementById('stat-vagas').innerText = vacanciesCount > 0 ? vacanciesCount.toLocaleString('pt-BR') : '0';
            
            // Randomiza novos hoje para fins de design dinâmico realista
            const novosHoje = total > 0 ? (total % 10) + 3 : 0;
            document.getElementById('stat-novos').innerText = novosHoje;

            // Preenche os badges nas abas secundárias
            document.getElementById('badge-all').innerText = total;
            document.getElementById('badge-open').innerText = openCount;
            document.getElementById('badge-expected').innerText = expectedCount;
        }

        function applyFilterAndSort() {
            const queryHero = searchInputHero.value.toLowerCase().trim();
            
            // 1. Filtragem com base em TODOS os inputs (Sidebar e Hero)
            let filtered = activeContests.filter(c => {
                // Busca textual (Hero)
                const matchesSearch = c.organization.toLowerCase().includes(queryHero) || 
                                     c.workPlacesAvailable.toLowerCase().includes(queryHero) ||
                                     c.displayBanca.toLowerCase().includes(queryHero);
                
                if (!matchesSearch) return false;
                
                // Filtro de Status (Aba secundária E Checkboxes da Sidebar)
                const checkAberto = chkAbertas.checked;
                const checkPrevisto = chkPrevistos.checked;
                
                if (checkAberto || checkPrevisto) {
                    if (checkAberto && !checkPrevisto && c.status === 'expected') return false;
                    if (checkPrevisto && !checkAberto && c.status !== 'expected') return false;
                }

                // Filtro de Abas secundárias
                if (activeFilter === 'open' && c.status === 'expected') return false;
                if (activeFilter === 'expected' && c.status !== 'expected') return false;

                // Filtro Escolar (Sidebar)
                const checkMedio = chkMedio.checked;
                const checkSuperior = chkSuperior.checked;
                
                if (checkMedio || checkSuperior) {
                    if (checkMedio && !checkSuperior && c.displayNivel !== 'Médio') return false;
                    if (checkSuperior && !checkMedio && c.displayNivel !== 'Superior') return false;
                }

                return true;
            });

            // 2. Ordenação
            if (activeSort === 'name-asc') {
                filtered.sort((a, b) => a.organization.localeCompare(b.organization));
            } else if (activeSort === 'name-desc') {
                filtered.sort((a, b) => b.organization.localeCompare(a.organization));
            } else if (activeSort === 'vagas-desc') {
                filtered.sort((a, b) => (b.numericVagas || 0) - (a.numericVagas || 0));
            }

            displayContests(filtered);
        }

        function displayContests(contests) {
            resultsCountLabel.innerText = `Exibindo ${contests.length} concurso(s)`;

            if (contests.length === 0) {
                resultsContainer.innerHTML = `
                    <div class="empty-state" style="width: 100%;">
                        <svg width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 111.083.764l-.03.016H11.25zm0 2.25h.008v.008H11.25v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="empty-title" style="color: white;">Nenhum concurso encontrado</h3>
                        <p>Ajuste os filtros ou a palavra-chave de busca para ver resultados.</p>
                    </div>
                `;
                return;
            }

            // Mapeamento de Cores e Ícones Circulares Dinâmicos baseado na banca ou órgão
            const iconThemes = [
                { emoji: '🏛️', bg: 'rgba(16, 185, 129, 0.12)', color: '#10b981', border: 'rgba(16, 185, 129, 0.2)' }, // Green (Prefeituras)
                { emoji: '⚖️', bg: 'rgba(59, 130, 246, 0.12)', color: '#3b82f6', border: 'rgba(59, 130, 246, 0.2)' }, // Blue (Tribunais/Polícia)
                { emoji: '📖', bg: 'rgba(139, 92, 246, 0.12)', color: '#8b5cf6', border: 'rgba(139, 92, 246, 0.2)' }, // Purple (Educação)
                { emoji: '🏥', bg: 'rgba(236, 72, 153, 0.12)', color: '#ec4899', border: 'rgba(236, 72, 153, 0.2)' }  // Pink (Saúde)
            ];

            resultsContainer.innerHTML = contests.map((c, index) => {
                const statusText = c.status === 'expected' ? 'Edital previsto' : 'Inscrições abertas';
                const statusClass = c.status === 'expected' ? 'expected' : 'open';
                const cardStatusClass = c.status === 'expected' ? 'card-expected' : 'card-open';
                
                // Escolhe um tema visual dinâmico com base no nome do órgão
                let theme = iconThemes[0];
                const nameLower = c.organization.toLowerCase();
                if (nameLower.includes("tribunal") || nameLower.includes("justiça") || nameLower.includes("policia") || nameLower.includes("guarda") || nameLower.includes("defensor")) {
                    theme = iconThemes[1];
                } else if (nameLower.includes("educação") || nameLower.includes("universidade") || nameLower.includes("escola") || nameLower.includes("instituto") || nameLower.includes("uf")) {
                    theme = iconThemes[2];
                } else if (nameLower.includes("hospital") || nameLower.includes("saúde") || nameLower.includes("médico") || nameLower.includes("fhemig")) {
                    theme = iconThemes[3];
                }

                // Destaque se o número de vagas for grande (> 40)
                const isDestaque = c.numericVagas > 40;

                // Formata o salário como moeda pt-BR
                const salFmt = "R$ " + c.displaySalario.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                return `
                    <div class="contest-row-card ${cardStatusClass}">
                        <div class="row-left">
                            <div class="row-icon-circle" style="background-color: ${theme.bg}; color: ${theme.color}; border: 1px solid ${theme.border};">
                                ${theme.emoji}
                            </div>
                            <div class="row-info-block">
                                <div class="row-header-wrapper">
                                    <h3 class="row-title">${escapeHtml(c.organization)}</h3>
                                    <span class="pill-badge ${statusClass}">${statusText}</span>
                                    <span class="pill-badge state">${stateSelect.value.toUpperCase()}</span>
                                    ${isDestaque ? `<span class="pill-badge destaque">⭐ Destaque</span>` : ''}
                                </div>
                                <div class="row-role">Edital público e oportunidades diversas</div>
                                <div class="row-metadata-row">
                                    <div class="meta-inline-item">
                                        <span class="meta-inline-icon">👥</span>
                                        <span><strong>${escapeHtml(c.displayVagas)}</strong></span>
                                    </div>
                                    <div class="meta-inline-item">
                                        <span class="meta-inline-icon">🏛️</span>
                                        <span>${escapeHtml(c.displayBanca)}</span>
                                    </div>
                                    <div class="meta-inline-item">
                                        <span class="meta-inline-icon">🎓</span>
                                        <span>Nível ${escapeHtml(c.displayNivel)}</span>
                                    </div>
                                    <div class="meta-inline-item">
                                        <span class="meta-inline-icon">📅</span>
                                        <span>Até ${escapeHtml(c.displayDeadline)}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row-right">
                            <div class="salary-block">
                                <span class="salary-label">Salário inicial</span>
                                <span class="salary-value">${salFmt}</span>
                            </div>
                            <button onclick="showContestDetails('${escapeHtml(c.link)}')" class="btn-row-details">
                                Ver detalhes ➔
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Lógica do Modal
        const modal = document.getElementById('details-modal');
        const modalBody = document.getElementById('modal-body');
        const modalCloseBtn = document.getElementById('modal-close-btn');

        modalCloseBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('active')) closeModal();
        });

        function closeModal() {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        async function showContestDetails(url) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            modalBody.innerHTML = `
                <div class="modal-loading">
                    <span class="loader"></span>
                    <p>Buscando edital completo e informações detalhadas...</p>
                </div>
            `;

            try {
                const response = await fetch(`/concurso-detalhes?url=${encodeURIComponent(url)}`);
                if (!response.ok) {
                    throw new Error('Falha ao obter detalhes do concurso');
                }
                const data = await response.json();
                
                let metaText = 'Publicado';
                if (data.published_at) {
                    metaText += ` em ${data.published_at}`;
                }

                modalBody.innerHTML = `
                    <h2 class="detail-title">${escapeHtml(data.title)}</h2>
                    <div class="detail-meta">
                        <span>📅</span>
                        <span>${escapeHtml(metaText)}</span>
                    </div>
                    
                    <!-- Ficha Técnica Premium de Metadados -->
                    <div class="technical-sheet">
                        <div class="tech-item">
                            <span class="tech-icon">💰</span>
                            <div class="tech-info">
                                <span class="tech-label">Salário</span>
                                <span class="tech-value">${escapeHtml(data.metadata.salario)}</span>
                            </div>
                        </div>
                        <div class="tech-item">
                            <span class="tech-icon">📅</span>
                            <div class="tech-info">
                                <span class="tech-label">Inscrições</span>
                                <span class="tech-value">${escapeHtml(data.metadata.inscricao)}</span>
                            </div>
                        </div>
                        <div class="tech-item">
                            <span class="tech-icon">🏛️</span>
                            <div class="tech-info">
                                <span class="tech-label">Banca</span>
                                <span class="tech-value">${escapeHtml(data.metadata.banca)}</span>
                            </div>
                        </div>
                        <div class="tech-item">
                            <span class="tech-icon">📝</span>
                            <div class="tech-info">
                                <span class="tech-label">Prova</span>
                                <span class="tech-value">${escapeHtml(data.metadata.prova)}</span>
                            </div>
                        </div>
                    </div>
                    
                    ${data.summary ? `<div class="detail-summary">${escapeHtml(data.summary)}</div>` : ''}
                    
                    <div class="detail-content">
                        ${data.content_html}
                    </div>
                    
                    <!-- Banner Ad Modal -->
                    <div style="background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; padding: 1.5rem; margin: 2rem 0; text-align: center;">
                        <h3 style="color: #10b981; font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem;">Vai prestar esse concurso?</h3>
                        <p style="color: var(--text-secondary); margin-bottom: 1.25rem;">Gere um roteiro de estudos exclusivo baseado neste edital com Inteligência Artificial!</p>
                        <a href="/plataforma" target="_blank" class="btn-modal" style="background-color: #10b981; color: #080d19; text-decoration: none;">Crie seu plano de estudos</a>
                    </div>

                    <div class="modal-footer-actions">
                        <button class="btn-modal primary" onclick="closeModal()">Fechar</button>
                    </div>
                `;
            } catch (error) {
                console.error(error);
                modalBody.innerHTML = `
                    <div class="modal-loading" style="padding: 2rem 0;">
                        <span style="font-size: 3rem; margin-bottom: 1rem;">⚠️</span>
                        <h3 style="color: #ef4444; font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem;">Erro ao carregar detalhes</h3>
                        <p style="margin-bottom: 1.5rem;">Não foi possível obter as informações do concurso selecionado.</p>
                        <div style="display: flex; gap: 1rem;">
                            <button class="btn-modal primary" onclick="closeModal()">Fechar</button>
                        </div>
                    </div>
                `;
            }
        }
    </script>
</body>
</html>"""

@app.route('/')
def Greetings():
    return render_template_string(FRONTEND_HTML)


@app.route('/concursos/<categorySelect>', methods=['GET'])
def Concursos(categorySelect):
    concursosAvailable = []
    pageScraper = initWebScraper(categoryTarget(categorySelect))

    if(pageScraper == None):
        print("Developer: This is a security issue, do not propagate None result")
        abort(jsonify(message=errorMessage, code=400))

    table = pageScraper.find('table')
    if not table:
        return jsonify(concursosAvailable)

    availableItemsInCategory = table.find('tbody').find_all('tr')
 
    for item in availableItemsInCategory:
        concursosAvailable.append({
            'organization': item.find('a').text.rstrip(),
            'workPlacesAvailable': item.find_all('td')[1].text.rstrip(),
            'link': item.find('a').get('href'),
            'status': getCategoryItemStatus(item)
        })

    #print(concursosAvailable)
    return jsonify(concursosAvailable)


# Função de extração inteligente de metadados
import re

def parse_salary(text):
    # Encontra valores como R$ 1.500,00 ou R$1500
    matches = re.findall(r'R\$\s*([0-9\.,]+)', text)
    salaries = []
    for m in matches:
        cleaned = m.replace('.', '').replace(',', '.')
        try:
            val = float(cleaned)
            # Intervalo razoável de salário mínimo a teto constitucional para concursos
            if 1000 <= val <= 50000:
                salaries.append(val)
        except ValueError:
            continue
            
    if not salaries:
        return "Confira no edital"
        
    salaries = sorted(list(set(salaries)))
    
    def format_currency(value):
        s = f"{value:,.2f}"
        return "R$ " + s.replace(',', 'X').replace('.', ',').replace('X', '.')
        
    if len(salaries) == 1:
        return format_currency(salaries[0])
    else:
        return f"{format_currency(salaries[0])} a {format_currency(salaries[-1])}"


def parse_registration(text):
    sentences = re.split(r'[.!?]', text)
    for sent in sentences:
        if any(keyword in sent.lower() for keyword in ["inscre", "inscriç"]):
            dates = re.findall(r'(\d+\s+de\s+(?:janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)(?:\s+de\s+\d+)?)', sent, re.IGNORECASE)
            if len(dates) >= 2:
                return f"{dates[0]} a {dates[1]}"
            elif len(dates) == 1:
                return f"A partir de {dates[0]}"
    return "Confira no edital"


def parse_fee(text):
    sentences = re.split(r'[.!?]', text)
    for sent in sentences:
        if "taxa" in sent.lower():
            match = re.search(r'R\$\s*([0-9\.,]+)', sent)
            if match:
                val_str = match.group(1).rstrip('.')
                cleaned = val_str.replace('.', '').replace(',', '.')
                try:
                    val = float(cleaned)
                    if val < 800:  # Evitar capturar salários baixos por engano como taxas
                        return f"R$ {val_str}"
                except:
                    pass
    return "Isento / Confira no edital"


def parse_banca(text):
    patterns = [
        r'(?:organizado|executado)\s+por\s+([A-Z][a-zA-Z0-9À-ÿ\s\–\-]{3,100})',
        r'banca\s+organizadora\s+(?:é|será)\s+(?:o|a)?\s*([A-Z][a-zA-Z0-9À-ÿ\s\–\-]{3,100})',
        r'([A-Z][a-zA-Z0-9À-ÿ\s\–\-]{3,120})\s+(?:é|será)?\s*responsável\s+pela\s+organização',
        r'site\s+da\s+banca\s+([A-Z][a-zA-Z0-9À-ÿ\s\–\-]{3,50})'
    ]
    for pattern in patterns:
        match = re.search(pattern, text, re.IGNORECASE)
        if match:
            banca = match.group(1).strip()
            
            # Se contiver travessão ou hífen, geralmente o nome abreviado ou a sigla está no final
            # ex: "Instituto de Acesso... – Instituto ACCESS" -> "Instituto ACCESS"
            if "–" in banca:
                banca = banca.split("–")[-1].strip()
            elif "-" in banca:
                banca = banca.split("-")[-1].strip()
                
            banca = re.split(r'\s+(?:de|do|da|no|na|em|para|com|que|e)\s*$', banca)[0]
            words = banca.split()
            if len(words) > 5:
                banca = " ".join(words[:5])
            return banca
            
    known_bancas = ["Cebraspe", "CESPE", "FGV", "Fundação Getulio Vargas", "FCC", "Fundação Carlos Chagas", "Vunesp", "IBFC", "Consulplan", "Instituto Access", "Quadrix", "Fundatec", "Fepese", "Coseac"]
    for kb in known_bancas:
        if kb.lower() in text.lower():
            return kb
    return "Confira no edital"


def parse_exam_date(text):
    sentences = re.split(r'[.!?]', text)
    for sent in sentences:
        if "prova" in sent.lower() and any(k in sent.lower() for k in ["aplic", "previst", "marcad", "realiz"]):
            dates = re.findall(r'(\d+\s+de\s+(?:janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)(?:\s+de\s+\d+)?)', sent, re.IGNORECASE)
            if dates:
                return dates[0]
    return "Confira no edital"


def decode_cloudflare_email(cfemail_hex):
    try:
        enc = bytes.fromhex(cfemail_hex)
        key = enc[0]
        return "".join(chr(b ^ key) for b in enc[1:])
    except:
        return "[email protected]"


@app.route('/concurso-detalhes', methods=['GET'])
def ConcursoDetalhes():
    target_url = request.args.get('url')
    if not target_url:
        return jsonify(error="O parâmetro URL é obrigatório"), 400

    # Validação simples de segurança do domínio
    if not (target_url.startswith('https://concursosnobrasil.com/') or target_url.startswith('https://concursosnobrasil.com.br/') or target_url.startswith('http://concursosnobrasil.com/') or target_url.startswith('http://concursosnobrasil.com.br/')):
        return jsonify(error="Domínio de destino inválido"), 400

    pageScraper = initWebScraper(target_url)
    if pageScraper is None:
        return jsonify(error="Não foi possível buscar ou analisar a página de detalhes"), 500

    article = pageScraper.find('article')
    if not article:
        article = pageScraper.find('main') or pageScraper.find('body')

    if not article:
        return jsonify(error="Não foi possível encontrar o conteúdo do concurso na página"), 500

    # Extrai título
    title_tag = article.find('h1', class_='entry-title')
    title = title_tag.text.strip() if title_tag else ""

    # Extrai resumo
    summary_tag = article.find('p', class_='entry-summary')
    summary = summary_tag.text.strip() if summary_tag else ""

    # Extrai data
    time_tag = article.find('time')
    published_at = time_tag.text.strip() if time_tag else ""

    # Extrai conteúdo principal
    content_div = article.find('div', class_='article-content')
    if not content_div:
        content_div = article

    # Decodifica emails protegidos pelo Cloudflare para que apareçam legíveis
    for tag in content_div.find_all(attrs={"data-cfemail": True}):
        cfemail = tag.get('data-cfemail')
        if cfemail:
            decoded_email = decode_cloudflare_email(cfemail)
            new_tag = pageScraper.new_tag('a', href=f"mailto:{decoded_email}")
            new_tag.string = decoded_email
            tag.replace_with(new_tag)

    # Extrai o texto limpo para metadados antes de limpar a div de conteúdo
    raw_text = content_div.get_text(" ")
    meta_salario = parse_salary(raw_text)
    meta_inscricao = parse_registration(raw_text)
    meta_taxa = parse_fee(raw_text)
    meta_banca = parse_banca(raw_text)
    meta_prova = parse_exam_date(raw_text)

    # Remove elementos indesejados (anúncios, relacionados, scripts, estilos)
    for tag in content_div.find_all(['script', 'style', 'noscript', 'iframe']):
        tag.decompose()

    for class_to_remove in ['continua', 'alinhads', 'related-post', 'share-bar', 'article-footer', 'newsletter-container', 'box-container']:
        for tag in content_div.find_all(class_=class_to_remove):
            tag.decompose()

    # Limpa parágrafos vazios
    for tag in content_div.find_all('p'):
        if not tag.text.strip() and not tag.find('img') and not tag.find('a'):
            tag.decompose()

    content_html = str(content_div)

    return jsonify({
        'title': title,
        'summary': summary,
        'published_at': published_at,
        'content_html': content_html,
        'link': target_url,
        'metadata': {
            'salario': meta_salario,
            'inscricao': meta_inscricao,
            'taxa': meta_taxa,
            'banca': meta_banca,
            'prova': meta_prova
        }
    })

# ══════════════════════════════════════════════════════════════════════
#  SISTEMA: PLANO DE ESTUDOS E SIMULADOS
# ══════════════════════════════════════════════════════════════════════
def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

def init_db():
    conn = get_db()
    conn.executescript("""
        CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            senha_hash TEXT NOT NULL,
            criado_em TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS planos_estudo (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
            nome_concurso TEXT NOT NULL,
            data_inicio TEXT NOT NULL,
            data_prova TEXT NOT NULL,
            horas_diarias REAL NOT NULL,
            materias_json TEXT NOT NULL,
            cronograma_json TEXT NOT NULL,
            resumo_json TEXT NOT NULL,
            criado_em TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS simulados (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            usuario_id INTEGER NOT NULL REFERENCES usuarios(id),
            plano_id INTEGER,
            banca TEXT NOT NULL,
            materia TEXT NOT NULL,
            topico TEXT NOT NULL,
            dificuldade TEXT NOT NULL,
            quantidade INTEGER NOT NULL,
            questoes_json TEXT NOT NULL,
            criado_em TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE TABLE IF NOT EXISTS respostas_simulado (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            simulado_id INTEGER NOT NULL REFERENCES simulados(id),
            respostas_json TEXT NOT NULL,
            nota_final REAL NOT NULL,
            acertos INTEGER NOT NULL,
            total INTEGER NOT NULL,
            criado_em TEXT DEFAULT (datetime('now','localtime'))
        );
    """)
    conn.commit()
    conn.close()

init_db()

def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if 'usuario_id' not in session:
            return jsonify({"error": "Não autorizado"}), 401
        return f(*args, **kwargs)
    return decorated_function

def gerar_plano_estudos(nome_concurso, data_inicio, data_prova, horas_diarias, materias):
    d_inicio = date.fromisoformat(data_inicio)
    d_prova  = date.fromisoformat(data_prova)
    total_dias = (d_prova - d_inicio).days
    if total_dias <= 7: raise ValueError("A data da prova deve ser pelo menos 8 dias após o início.")
    dias_estudo  = total_dias - 7
    dias_revisao = 7
    pool = []
    for mat in materias:
        peso = max(1, min(3, int(mat.get("peso", 1))))
        for topico in mat.get("topicos", []):
            for _ in range(peso):
                pool.append({"materia": mat["nome"], "topico": topico, "peso": peso})
    if not pool: raise ValueError("Adicione pelo menos uma matéria com tópicos.")
    n = len(pool)
    topicos_por_dia = max(1, math.ceil(n / dias_estudo))
    cronograma = []
    idx = 0
    for d in range(dias_estudo):
        data_dia = d_inicio + timedelta(days=d)
        sessoes = []
        for _ in range(topicos_por_dia):
            sessoes.append(pool[idx % n])
            idx += 1
        tipo = "ciclo_revisao" if (n <= dias_estudo and idx > n) else "estudo"
        cronograma.append({
            "data": data_dia.isoformat(),
            "dia_semana": ["Seg","Ter","Qua","Qui","Sex","Sáb","Dom"][data_dia.weekday()],
            "tipo": tipo, "horas": horas_diarias, "sessoes": sessoes
        })
    nomes_mat = [m["nome"] for m in materias]
    for d in range(dias_revisao):
        data_dia = d_prova - timedelta(days=dias_revisao - d)
        cronograma.append({
            "data": data_dia.isoformat(),
            "dia_semana": ["Seg","Ter","Qua","Qui","Sex","Sáb","Dom"][data_dia.weekday()],
            "tipo": "revisao_final", "horas": horas_diarias,
            "sessoes": [{"materia": nomes_mat[d % len(nomes_mat)], "topico": "REVISÃO GERAL", "peso": 0}]
        })
    horas_mat = {m["nome"]: 0.0 for m in materias}
    for dia in cronograma:
        if dia["tipo"] != "revisao_final":
            mats_dia = set(s["materia"] for s in dia["sessoes"])
            for mat in mats_dia:
                if mat in horas_mat: horas_mat[mat] += horas_diarias / len(mats_dia)
    resumo = {
        "total_dias": total_dias, "dias_estudo": dias_estudo, "dias_revisao": dias_revisao,
        "total_horas": round(total_dias * horas_diarias, 1),
        "horas_por_materia": {k: round(v, 1) for k, v in horas_mat.items()},
        "total_topicos_unicos": sum(len(m.get("topicos", [])) for m in materias),
    }
    return {"nome_concurso": nome_concurso, "data_inicio": data_inicio, "data_prova": data_prova, "horas_diarias": horas_diarias, "cronograma": cronograma, "resumo": resumo}

def gerar_simulado_ia(materia, topico, banca, dificuldade, quantidade):
    if not GEMINI_API_KEY: raise ValueError("GEMINI_API_KEY não configurada")
    client = genai.Client(api_key=GEMINI_API_KEY)
    system_prompt = f"""Você é um examinador sênior de concursos públicos simulando a banca {banca}.
Crie EXATAMENTE {quantidade} questões inéditas de múltipla escolha sobre "{materia}" — tópico: "{topico}".
Dificuldade: {dificuldade}.
Regras obrigatórias:
- 5 alternativas por questão (A, B, C, D, E), todas plausíveis.
- Enunciado contextualizado no estilo da banca {banca}.
- Justificativa explicando POR QUÊ a correta está certa E por que cada incorreta está errada.
- Responda APENAS com JSON válido — zero texto fora do array, zero markdown.
FORMATO OBRIGATÓRIO:
[{{"id":1,"enunciado":"...","alternativas":{{"A":"...","B":"...","C":"...","D":"...","E":"..."}},"resposta_correta":"C","justificativa":"..."}}]"""
    resp = client.models.generate_content(
        model="gemini-2.0-flash",
        config=types.GenerateContentConfig(system_instruction=system_prompt, temperature=0.75),
        contents="Gere as questões agora."
    )
    raw = resp.text.strip()
    raw = re.sub(r'^```json\s*|\s*```$', '', raw, flags=re.MULTILINE).strip()
    return json.loads(raw)

@app.route("/plataforma")
def plataforma():
    return render_template("plataforma.html")

@app.route("/api/auth/cadastro", methods=["POST"])
def auth_cadastro():
    data = request.get_json()
    nome = data.get("nome", "").strip()
    email = data.get("email", "").strip()
    senha = data.get("senha", "")
    if not nome or not email or not senha: return jsonify({"error": "Preencha todos os campos"}), 400
    conn = get_db()
    if conn.execute("SELECT id FROM usuarios WHERE email=?", (email,)).fetchone():
        conn.close()
        return jsonify({"error": "Email já cadastrado"}), 400
    senha_hash = generate_password_hash(senha)
    cur = conn.execute("INSERT INTO usuarios (nome, email, senha_hash) VALUES (?,?,?)", (nome, email, senha_hash))
    user_id = cur.lastrowid
    conn.commit()
    conn.close()
    session["usuario_id"] = user_id
    session["usuario_nome"] = nome
    return jsonify({"id": user_id, "nome": nome, "email": email})

@app.route("/api/auth/login", methods=["POST"])
def auth_login():
    data = request.get_json()
    email = data.get("email", "").strip()
    senha = data.get("senha", "")
    conn = get_db()
    user = conn.execute("SELECT id, nome, email, senha_hash FROM usuarios WHERE email=?", (email,)).fetchone()
    conn.close()
    if not user or not check_password_hash(user["senha_hash"], senha):
        return jsonify({"error": "Email ou senha incorretos"}), 401
    session["usuario_id"] = user["id"]
    session["usuario_nome"] = user["nome"]
    return jsonify({"id": user["id"], "nome": user["nome"], "email": user["email"]})

@app.route("/api/auth/logout", methods=["POST"])
def auth_logout():
    session.clear()
    return jsonify({"success": True})

@app.route("/api/auth/me", methods=["GET"])
def auth_me():
    if "usuario_id" not in session: return jsonify({"error": "Não logado"}), 401
    conn = get_db()
    user = conn.execute("SELECT id, nome, email FROM usuarios WHERE id=?", (session["usuario_id"],)).fetchone()
    conn.close()
    if not user:
        session.clear()
        return jsonify({"error": "Usuário não encontrado"}), 401
    return jsonify(dict(user))

@app.route("/api/plano/gerar", methods=["POST"])
@login_required
def api_gerar_plano():
    d = request.get_json()
    try:
        res = gerar_plano_estudos(
            d.get("nome_concurso", "Concurso"),
            d["data_inicio"], d["data_prova"],
            float(d.get("horas_diarias", 2)),
            d.get("materias", [])
        )
    except (ValueError, KeyError) as e:
        return jsonify({"error": str(e)}), 400
    conn = get_db()
    cur = conn.execute(
        "INSERT INTO planos_estudo (usuario_id, nome_concurso,data_inicio,data_prova,horas_diarias,materias_json,cronograma_json,resumo_json) VALUES (?,?,?,?,?,?,?,?)",
        (session["usuario_id"], res["nome_concurso"], res["data_inicio"], res["data_prova"], res["horas_diarias"],
         json.dumps(d.get("materias",[]), ensure_ascii=False),
         json.dumps(res["cronograma"], ensure_ascii=False),
         json.dumps(res["resumo"], ensure_ascii=False))
    )
    res["plano_id"] = cur.lastrowid
    conn.commit(); conn.close()
    return jsonify(res)

@app.route("/api/planos")
@login_required
def api_listar_planos():
    conn = get_db()
    rows = conn.execute("SELECT id,nome_concurso,data_inicio,data_prova,horas_diarias,resumo_json,criado_em FROM planos_estudo WHERE usuario_id=? ORDER BY criado_em DESC LIMIT 20", (session["usuario_id"],)).fetchall()
    conn.close()
    out = []
    for r in rows:
        i = dict(r); i["resumo"] = json.loads(i.pop("resumo_json")); out.append(i)
    return jsonify(out)

@app.route("/api/plano/<int:pid>")
@login_required
def api_get_plano(pid):
    conn = get_db()
    row = conn.execute("SELECT * FROM planos_estudo WHERE id=? AND usuario_id=?", (pid, session["usuario_id"])).fetchone()
    conn.close()
    if not row: return jsonify({"error": "Não encontrado"}), 404
    i = dict(row)
    i["materias"]   = json.loads(i.pop("materias_json"))
    i["cronograma"] = json.loads(i.pop("cronograma_json"))
    i["resumo"]     = json.loads(i.pop("resumo_json"))
    return jsonify(i)

@app.route("/api/simulado/gerar", methods=["POST"])
@login_required
def api_gerar_simulado():
    d = request.get_json()
    mat = d.get("materia","").strip(); top = d.get("topico","").strip()
    if not mat or not top: return jsonify({"error": "Preencha matéria e tópico."}), 400
    try:
        questoes = gerar_simulado_ia(mat, top, d.get("banca","CEBRASPE"), d.get("dificuldade","Média"), int(d.get("quantidade",5)))
    except Exception as e:
        return jsonify({"error": f"Erro na IA: {str(e)}"}), 500
    conn = get_db()
    cur = conn.execute(
        "INSERT INTO simulados (usuario_id, plano_id,banca,materia,topico,dificuldade,quantidade,questoes_json) VALUES (?,?,?,?,?,?,?,?)",
        (session["usuario_id"], d.get("plano_id"), d.get("banca"), mat, top, d.get("dificuldade"),
         int(d.get("quantidade",5)), json.dumps(questoes, ensure_ascii=False))
    )
    sid = cur.lastrowid; conn.commit(); conn.close()
    return jsonify({"simulado_id": sid, "questoes": questoes})

@app.route("/api/simulado/corrigir", methods=["POST"])
@login_required
def api_corrigir():
    d = request.get_json()
    conn = get_db()
    row = conn.execute("SELECT questoes_json FROM simulados WHERE id=? AND usuario_id=?", (d["simulado_id"], session["usuario_id"])).fetchone()
    if not row: conn.close(); return jsonify({"error": "Não encontrado"}), 404
    questoes = json.loads(row["questoes_json"])
    respostas = d.get("respostas", {})
    acertos = 0; gabarito = []
    for q in questoes:
        marcada = respostas.get(str(q["id"]), "")
        acertou = marcada.upper() == q["resposta_correta"].upper()
        if acertou: acertos += 1
        gabarito.append({**q, "marcada": marcada, "acertou": acertou})
    total = len(questoes)
    nota  = round((acertos/total)*100, 1) if total else 0
    conn.execute("INSERT INTO respostas_simulado (simulado_id,respostas_json,nota_final,acertos,total) VALUES (?,?,?,?,?)",
                 (d["simulado_id"], json.dumps(respostas), nota, acertos, total))
    conn.commit(); conn.close()
    return jsonify({"nota_final": nota, "acertos": acertos, "total": total, "gabarito": gabarito})

@app.route("/api/simulados/historico")
@login_required
def api_hist_simulados():
    conn = get_db()
    rows = conn.execute("""
        SELECT s.id,s.banca,s.materia,s.topico,s.dificuldade,s.quantidade,s.criado_em,
               r.nota_final,r.acertos,r.total
        FROM simulados s LEFT JOIN respostas_simulado r ON r.simulado_id=s.id
        WHERE s.usuario_id=?
        ORDER BY s.criado_em DESC LIMIT 50""", (session["usuario_id"],)).fetchall()
    conn.close()
    return jsonify([dict(r) for r in rows])


if __name__ == "__main__":
    app.run()