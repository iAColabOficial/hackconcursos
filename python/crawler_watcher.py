"""
HackConcursos - Crawler Watcher
Execute este script no terminal e deixe rodando.
Ele monitora os pedidos feitos pelo painel Admin e executa o robô automaticamente.

Como usar:
    python python/crawler_watcher.py
"""
import json
import os
import time
import subprocess
import sys

JOB_FILE = os.path.join("uploads", "crawler_job.json")
CRAWLER_SCRIPT = os.path.join(os.path.dirname(__file__), "crawler_pci.py")
MASTER_STATE_FILE = os.path.join("uploads", "master_state.json")

def get_master_page():
    if os.path.exists(MASTER_STATE_FILE):
        try:
            with open(MASTER_STATE_FILE, "r") as f:
                return json.load(f).get("next_page", 1)
        except: return 1
    return 1

def save_master_page(page):
    with open(MASTER_STATE_FILE, "w") as f:
        json.dump({"next_page": page, "last_update": time.time()}, f)

print("=" * 50, flush=True)
print("  HackConcursos - ORQUESTRADOR DE MINERAÇÃO", flush=True)
print("  - Master Job: Ativo (Minerando tudo)", flush=True)
print("  - Intervenção: Prioritária", flush=True)
print("=" * 50, flush=True)

while True:
    # 1. Verificar se existe uma ordem prioritária (Job)
    if os.path.exists(JOB_FILE):
        try:
            with open(JOB_FILE, "r", encoding="utf-8") as f:
                job = json.load(f)
            
            term = job.get('term', '')
            mode = job.get('mode', 'map')
            max_pages = job.get('max_pages', 20)
            
            print(f"\n[!] PRIORIDADE: '{term}' | Download: {mode=='download'}", flush=True)
            os.remove(JOB_FILE)
            
            cmd = [sys.executable, CRAWLER_SCRIPT, term, f"--pages={max_pages}"]
            if mode == 'download': cmd.append('--download')
            
            subprocess.run(cmd)
            print(f"[✅ PRIORIDADE CONCLUÍDA] Retornando ao Master Job...", flush=True)
            
        except Exception as e:
            print(f"[ERRO JOB] {e}", flush=True)
            if os.path.exists(JOB_FILE): os.remove(JOB_FILE)
    
    # 2. Se não tem nada prioritário, executa um lote do Master Job
    else:
        start_p = get_master_page()
        end_p = start_p + 9 # Processar 10 páginas por vez
        print(f"\n[*] MASTER: Processando Paginas {start_p} ate {end_p}...", flush=True)
        
        # O Master Job agora processa um lote de 10 páginas
        cmd = [sys.executable, CRAWLER_SCRIPT, "", f"--start_page={start_p}", f"--pages={end_p}", "--download"]
        
        subprocess.run(cmd)
        
        # Avançar o contador
        save_master_page(end_p + 1)
        print(f"[#] MASTER: Lote ate pagina {end_p} concluido.", flush=True)

    time.sleep(2)
