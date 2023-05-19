
# Setup DEV

1. Unpack `setup/glpi.tgz` to the root folder (the package already contains the `glpi` folder)
2. Set your webroot to `glpi`
3. Install **Glpi** (make sure you access Glpi via https)
4. Unpack the plugins you need (probably all of them) from `setup/plugins` to `glpi/plugins`
5. Run `./setup/setup-dev`
6. Install and enable the plugins you need from Setup->Plugins menu ([iService.domain]/front/plugin.php)
7. Choose "No" to close the "Switch to marketplace" block
