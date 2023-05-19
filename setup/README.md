# Setup DEV

1. Unpack `setup/glpi.tgz` to the root folder (the package already contains the `glpi` folder)
2. Set your webroot to `glpi`
3. Install **Glpi** (make sure you access Glpi via https)
4. Unpack the plugins you need (probably all of them) from `setup/plugins` to `glpi/plugins`
5. Run `./setup/setup-dev`
6. Install and enable the plugins you need from Setup->Plugins menu ([iService.domain]/front/plugin.php)
7. Choose "No" to close the "Switch to marketplace" block

# Setup PROD and TEST

1. Copy and unpack `setup/glpi.tgz` to the root folder of your webserver (beware that the package contains a `glpi` folder).
2. Install **Glpi** (make sure you access Glpi via https)
3. Unpack the plugins you need (probably all of them) from `setup/plugins` to the `plugins` folder of your Glpi installation
4. Run the deployment to PROD or TEST (see [below](#deployment-to-prod-or-test-with-deploybot))
5. Install and enable the plugins you need from Setup->Plugins menu ([iService.domain]/front/plugin.php)
6. Choose "No" to close the "Switch to marketplace" block

# Deployment to PROD or TEST with DeployBot

## Setup DeployBot

1. Connect your GitHub account to DeployBot
2. In the repository settings, connect to your repository
3. Add the environments you need (PROD and TEST)
4. Set them to deploy automatically
5. Set up the servers where the files will be deployed to.
6. Exclude the following paths from being uploaded
```
node_modules
**/node_modules
glpi
setup
README.md
```
7. Since the deployment contains the plugin files in a `plugin` folder, this must be rsync-ed to the plugins folder of the Glpi installation. To do this, add the following post-deploy script:
```
sudo rsync -a [deployment_path]/plugin/ [webroot]/plugins/iService
sudo chown -R [user:group] [webroot]/plugins/iService
```
example:
```
sudo rsync -a ~/iService3-deploy/staging/plugin/ /var/www/clients/client1/web15/web/plugins/iService
sudo chown -R web15:client1 /var/www/clients/client1/web15/web/plugins/iService
```

## Deploy with DeployBot

- To deploy to PROD, merge and push your changes in the `prod` branch of this repository.
- To deploy to TEST, merge and push your changes in the `test` branch of this repository.

> DeployBot will automatically deploy the changes to the given environment.
