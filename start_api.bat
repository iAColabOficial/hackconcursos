@echo off
cd %~dp0concursosPublicosAPI-main
echo Instalando dependencias (caso nao estejam instaladas)...
pip install -r requirements.txt
echo.
echo Iniciando API do Radar de Concursos...
python app.py
pause
