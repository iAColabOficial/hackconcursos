import requests
from bs4 import BeautifulSoup
import mysql.connector
import os
import sys
import time
import json
import re

# Configuração do banco de dados
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': 'root',
    'database': 'estudoconcursos',
    'auth_plugin': 'mysql_native_password'
}

def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)

def download_real_pdf(url_gate, prova_id):
    active_file = os.path.join("uploads", "crawler_active.json")
    try:
        # Salva qual prova está sendo baixada agora para o monitor
        with open(active_file, "w") as f:
            json.dump({"id": prova_id}, f)

        session = requests.Session()
        headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'}
        
        # 1. Entrar na página do Gatekeeper
        res = session.get(url_gate, headers=headers, timeout=30)
        soup = BeautifulSoup(res.text, 'html.parser')
        
        # 2. Procurar o link direto para o PDF
        # O PCI costuma ter um link com texto "Download" ou um link direto .pdf
        pdf_link = None
        for a in soup.find_all('a', href=True):
            if '.pdf' in a['href'].lower():
                pdf_link = a['href']
                break
        
        if not pdf_link:
            # Tentar encontrar por padrões comuns de botões de download
            btn = soup.find('a', string=re.compile(r'Download', re.I))
            if btn: pdf_link = btn['href']

        if pdf_link:
            if not pdf_link.startswith('http'):
                pdf_link = "https://www.pciconcursos.com.br" + pdf_link
            
            # 3. Baixar o arquivo
            pdf_res = session.get(pdf_link, headers=headers, timeout=60)
            if pdf_res.status_code == 200:
                filename = f"prova_{prova_id}.pdf"
                path = os.path.join("uploads", "banco_provas", filename)
                os.makedirs(os.path.dirname(path), exist_ok=True)
                with open(path, "wb") as f:
                    f.write(pdf_res.content)
                
                if os.path.exists(active_file): os.remove(active_file)
                return f"uploads/banco_provas/{filename}"
        
        if os.path.exists(active_file): os.remove(active_file)
        return None
    except Exception as e:
        print(f"    [ERRO DOWNLOAD] {e}", flush=True)
        if os.path.exists(active_file): os.remove(active_file)
        return None

def crawl_pci(term, do_download=False, max_pages=20, start_page=1):
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    session = requests.Session()
    headers = {'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'}
    session.headers.update(headers)

    # Definição de URLs
    if not term:
        url_base = "https://www.pciconcursos.com.br/provas/pesquisa/"
    else:
        url_base = f"https://www.pciconcursos.com.br/provas/pesquisa/?prova={term}"
    
    range_pages = range(start_page, max_pages + 1) if max_pages >= start_page else [start_page]

    for page in range_pages:
        url = url_base if page == 1 else f"https://www.pciconcursos.com.br/provas/pesquisa/{page}"
        print(f"[*] {'Master' if not term else 'Busca'} - Pag {page} | URL: {url}", flush=True)
        
        res = None
        for attempt in range(3):
            try:
                res = session.get(url, timeout=30)
                break
            except:
                time.sleep(2)
        
        if res is None or res.status_code != 200: break
        
        soup = BeautifulSoup(res.text, 'html.parser')
        items = []

        # ESTRATÉGIA UNIFICADA: Procura todos os links de download
        links_dl = soup.find_all('a', href=re.compile(r'provas/download/'))
        print(f"  [*] Detectados {len(links_dl)} links de download na pág {page}", flush=True)

        for link in links_dl:
            url_gate = link['href']
            if not url_gate.startswith('http'):
                url_gate = "https://www.pciconcursos.com.br" + url_gate
            
            texto = link.get_text().strip()
            
            # Tentar extrair Órgão e Banca se estiverem em uma tabela
            row = link.find_parent('tr')
            if row:
                cols = row.find_all('td')
                if len(cols) >= 3:
                    items.append({
                        'cargo': texto,
                        'url': url_gate,
                        'ano': cols[1].get_text().strip(),
                        'banca': cols[2].get_text().strip(),
                        'orgao': cols[3].get_text().strip() if len(cols) > 3 else "N/A"
                    })
                    continue

            # Se não estiver em tabela, tenta extrair do texto
            items.append({
                'cargo': texto,
                'url': url_gate,
                'ano': re.search(r'\b(20\d{2})\b', texto).group(1) if re.search(r'\b(20\d{2})\b', texto) else "---",
                'banca': "PCI",
                'orgao': "Geral"
            })

        # Processar os itens encontrados
        for item in items:
            cargo, url_gate, ano, banca, orgao = item['cargo'], item['url'], item['ano'], item['banca'], item['orgao']
            
            # FILTRO DE ELITE / ANO / MUNICIPAL
            try:
                ano_int = int(re.sub(r'\D', '', ano))
                if ano_int < 2020:
                    print(f"  [-] Ignorado Ano: {ano} | {cargo[:40]}", flush=True)
                    continue
            except: continue

            cargo_l, orgao_l = cargo.lower(), orgao.lower()
            whitelist = [
                'policia', 'policial', 'delegado', 'agente', 'escrivao', 'investigador', 'bombeiro', 'penal', 'penitenciario',
                'correios', 'caixa', 'banco', 'bacen', 'receita', 'auditor', 'fiscal', 'analista', 'juiz', 'promotor', 
                'procurador', 'advogado', 'defensor', 'professor', 'docente', 'engenheiro', 'medico', 'especialista', 'perito'
            ]
            blacklist_municipal = ['prefeitura', 'municipal', 'camara', 'pref.']
            
            is_elite = any(k in (cargo_l + " " + orgao_l) for k in whitelist)
            is_municipal_teacher = ('professor' in cargo_l or 'docente' in cargo_l) and any(b in orgao_l for b in blacklist_municipal)
            
            if not is_elite or is_municipal_teacher:
                print(f"  [-] Ignorado Cargo: {cargo[:40]} | {orgao}", flush=True)
                continue

            print(f"  [+] ACEITO: {cargo[:50]} ({ano})", flush=True)
            if orgao == "Geral" and " - " in cargo:
                orgao = cargo.split(" - ")[-1].strip()

            cursor.execute("INSERT IGNORE INTO banco_provas (banca, orgao, cargo, ano, url_pdf) VALUES (%s, %s, %s, %s, %s)", 
                           (banca, orgao, cargo, int(ano) if ano.isdigit() else 0, url_gate))
            conn.commit()

            if do_download:
                cursor.execute("SELECT id, arquivo_local FROM banco_provas WHERE url_pdf = %s", (url_gate,))
                rec = cursor.fetchone()
                if rec and not rec['arquivo_local']:
                    print(f"    [!] Baixando: {cargo[:50]}...", flush=True)
                    path = download_real_pdf(url_gate, rec['id'])
                    if path:
                        cursor.execute("UPDATE banco_provas SET arquivo_local = %s WHERE id = %s", (path, rec['id']))
                        conn.commit()
        
        time.sleep(1)

    cursor.close()
    conn.close()

if __name__ == "__main__":
    term = sys.argv[1] if len(sys.argv) > 1 else ""
    do_dl = "--download" in sys.argv
    max_pages, start_page = 20, 1
    for arg in sys.argv:
        if arg.startswith('--pages='): max_pages = int(arg.split('=')[1])
        if arg.startswith('--start_page='): start_page = int(arg.split('=')[1])

    crawl_pci(term, do_dl, max_pages, start_page)
