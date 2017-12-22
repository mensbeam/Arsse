@echo off
setlocal
set base=%~dp0
set roboCommand=%1

rem get all arguments except the first
shift
set "args="
:parse
if "%~1" neq "" (
  set args=%args% %1
  shift
  goto :parse
)
if defined args set args=%args:~1%

if "%1"=="clean" (
    call "%base%vendor\bin\robo" "%roboCommand%" %args%
) else (
    call "%base%vendor\bin\robo" "%roboCommand%" -- %args%
)
