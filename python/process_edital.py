import os
import sys
import json
import google.generativeai as genai
from dotenv import load_dotenv

# Carregar variáveis de ambiente
load_dotenv()

# Configurar API Gemini
genai.configure(api_key=os.getenv("GEMINI_API_KEY"))

def process_edital(nome_concurso, texto_edital):
    model = genai.GenerativeModel('gemini-1.5-flash')
    
    prompt = f"""
    Aja como um especialista em concursos públicos e análise estratégica.
    Analise o texto do edital do concurso: {nome_concurso}.
    
    Extraia as matérias/disciplinas principais e atribua a cada uma:
    1. Nome da disciplina.
    2. Peso padrão (se não houver, use 1.0).
    3. Importância estratégica (nível de 1 a 10) baseado na incidência histórica para este tipo de concurso.
    
    Retorne APENAS um JSON puro no seguinte formato:
    {{
        "disciplinas": [
            {{"nome": "Direito Constitucional", "peso": 2.0, "importancia": 9}},
            ...
        ]
    }}
    
    Texto do Edital:
    {texto_edital[:10000]}  # Limitando para evitar estouro de tokens no teste
    """
    
    try:
        response = model.generate_content(prompt)
        # Limpar resposta para garantir que seja JSON puro
        content = response.text.strip()
        if content.startswith("```json"):
            content = content[7:-3].strip()
        return json.loads(content)
    except Exception as e:
        return {{"error": str(e)}}

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({{"error": "Argumentos insuficientes"}}))
        sys.exit(1)
        
    nome = sys.argv[1]
    texto = sys.argv[2]
    
    resultado = process_edital(nome, texto)
    print(json.dumps(resultado))
