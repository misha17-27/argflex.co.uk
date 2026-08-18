@echo off
rem ---------------------------------------------------------------
rem  Start the Arg Flex site locally.
rem  Double-click this file, then open http://localhost:8124/
rem  Close the window (or press Ctrl+C) to stop the server.
rem ---------------------------------------------------------------
setlocal

set "SITE=%~dp0"
set "PHP=D:\argflex\php\php.exe"

if not exist "%PHP%" (
    where php >nul 2>&1
    if errorlevel 1 (
        echo.
        echo   PHP not found at %PHP% and not on PATH.
        echo   Put a PHP build there, or install PHP and try again.
        echo.
        pause
        exit /b 1
    )
    set "PHP=php"
)

echo.
echo   Arg Flex - local server
echo   ----------------------------------------
echo   Open:  http://localhost:8124/
echo   Stop:  close this window or press Ctrl+C
echo.

start "" http://localhost:8124/
"%PHP%" -S localhost:8124 -t "%SITE%." "%SITE%router.php"

endlocal
