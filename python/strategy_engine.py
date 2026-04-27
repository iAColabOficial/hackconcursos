import mysql.connector
import json
import sys
import os
from difflib import SequenceMatcher

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

def similar(a, b):
    return SequenceMatcher(None, a.lower(), b.lower()).ratio()

def get_strategic_base(target_id, target_type='cargo'):
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    
    # 1. Buscar disciplinas conforme o tipo
    if target_type == 'biblioteca':
        cursor.execute("SELECT id, nome, peso_padrao as peso FROM biblioteca_disciplinas WHERE biblioteca_edital_id = %s", (target_id,))
    else:
        cursor.execute("SELECT id, nome, peso FROM disciplinas WHERE cargo_id = %s", (target_id,))
    
    disciplinas = cursor.fetchall()
    
    # 2. Buscar biblioteca de tópicos para cruzamento
    cursor.execute("SELECT nome, incidencia FROM biblioteca_topicos")
    biblioteca = cursor.fetchall()
    
    incidencia_map = {
        'alta': 3,
        'media': 2,
        'baixa': 1
    }
    
    plan_base = []
    
    for disc in disciplinas:
        # Buscar tópicos conforme o tipo
        if target_type == 'biblioteca':
            cursor.execute("SELECT id, nome FROM biblioteca_topicos WHERE biblioteca_disciplina_id = %s", (disc['id'],))
        else:
            cursor.execute("SELECT id, nome FROM topicos_edital WHERE disciplina_id = %s", (disc['id'],))
            
        topicos = cursor.fetchall()
        
        disc_strategy = {
            'disciplina_id': disc['id'],
            'nome': disc['nome'],
            'peso': float(disc['peso']),
            'topicos': []
        }
        
        for topico in topicos:
            best_match = None
            max_ratio = 0
            
            for item in biblioteca:
                ratio = similar(topico['nome'], item['nome'])
                if ratio > 0.85 and ratio > max_ratio:
                    max_ratio = ratio
                    best_match = item
            
            incidencia_score = incidencia_map.get(best_match['incidencia'], 1) if best_match else 1
            
            disc_strategy['topicos'].append({
                'id': topico['id'],
                'nome': topico['nome'],
                'incidencia_score': incidencia_score,
                'prioridade_final': float(disc['peso']) * incidencia_score
            })
            
        # Ordenar tópicos por prioridade dentro da disciplina
        disc_strategy['topicos'].sort(key=lambda x: x['prioridade_final'], reverse=True)
        plan_base.append(disc_strategy)
        
    # Ordenar disciplinas pelo peso e média de incidências
    plan_base.sort(key=lambda x: x['peso'] * (sum(t['incidencia_score'] for t in x['topicos']) / max(1, len(x['topicos']))), reverse=True)
    
    conn.close()
    return plan_base

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "ID não fornecido"}))
        sys.exit(1)
        
    target_id = sys.argv[1]
    target_type = sys.argv[2] if len(sys.argv) > 2 else 'cargo'
    
    try:
        resultado = get_strategic_base(target_id, target_type)
        print(json.dumps(resultado, ensure_ascii=False))
    except Exception as e:
        print(json.dumps({"error": str(e)}))
