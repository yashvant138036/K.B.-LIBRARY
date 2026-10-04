@echo off
title K.B. Library Management System Server
echo Starting K.B. Library Management System at http://localhost:8000 ...
start http://localhost:8000
"C:\Users\jiten\.gemini\antigravity\scratch\php\php.exe" -S localhost:8000 -t "%~dp0"
pause
