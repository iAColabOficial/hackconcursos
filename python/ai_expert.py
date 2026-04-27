import os
import sys
import json
import google.generativeai as genai
from dotenv import load_dotenv

# Carregar variáveis de ambiente
load_dotenv()

# Configurar API Gemini
genai.configure(api_key=os.getenv("GEMINI_API_KEY"))

def boost_strategy(banca, base_estrategica, user_performance=None):
    model = genai.GenerativeModel('gemini-1.5-flash')
    
    # Prepara os dados para o prompt
    base_json = json.dumps(base_estrategica, ensure_ascii=False)
    perf_json = json.dumps(user_performance, ensure_ascii=False) if user_performance else "Sem dados de performance ainda."
    
    prompt = f"""
    Aja como o Especialista Sênior em Concursos do HackConcursos.
    Sua missão é otimizar uma base estratégica de estudos para a banca: {banca}.
    
    BASE ESTRATÉGICA ATUAL:
    {base_json}
    
    PERFORMANCE DO USUÁRIO (Erros/Acertos):
    {perf_json}
    
    TAREFAS:
    1. Analise o padrão da banca {banca} para as disciplinas listadas.
    2. Identifique quais tópicos a {banca} costuma cobrar de forma mais complexa ou com pegadinhas.
    3. Sugira ajustes nos pesos se a banca tiver uma preferência clara por alguma disciplina.
    4. Crie "Dicas de Mestre" (estratégias específicas) para os 3 tópicos mais importantes.
    5. Se houver falhas recorrentes na performance do usuário, sugira uma intervenção imediata.
    
    Retorne APENAS um JSON no formato:
    {{
        "ajustes_peso": [
            {{"disciplina_id": 1, "novo_peso": 2.5, "motivo": "A banca X foca muito aqui"}}
        ],
        "dicas_mestre": [
            {{"topico_id": 10, "dica": "Cuidado com o artigo X, a banca sempre troca as palavras Y por Z"}}
        ],
        "intervencao_sugerida": "Texto da intervenção baseada nos erros do usuário",
        "padrao_banca": "Resumo do comportamento da banca para este concurso"
    }}
    """
    
    try:
        response = model.generate_content(prompt)
        content = response.text.strip()
        if content.startswith("```json"):
            content = content[7:-3].strip()
        return json.loads(content)
    except Exception as e:
        return {"error": str(e)}

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"error": "Argumentos insuficientes (banca e base_estrategica necessários)"}))
        sys.exit(1)
        
    banca = sys.argv[1]
    base_estrategica = json.loads(sys.argv[2])
    user_performance = json.loads(sys.argv[3]) if len(sys.argv) > 3 else None
    
    resultado = boost_strategy(banca, base_estrategica, user_performance)
    print(json.dumps(resultado, ensure_ascii=False))
