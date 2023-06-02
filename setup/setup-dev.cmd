@echo off
cd glpi\plugins
mklink /D iservice "..\..\plugin"
cd "..\.."