# Setup

See the setup instructions for the DEV, TEST and PROD environments [here](setup/README.md)

# Useful tips

- The glpi system uses [Tabler Icons](https://tabler-icons.io) and [Font Awesome](https://fontawesome.com) for icons. You can find the icon names in the websites.

# GLPI hacks

- Update `handleUserMentions()` method in `glpi/src/RichText/UserMention.php` line 80 to: `$previous_value = $item->fields[$content_field] ?? null`;
- If "Find menu is not working" update `glpi/src/Html.php` line 6664 to: `if (is_array($menu) && strlen($menu['title'] ?? '') > 0) {`
- Add at the beginning of the `SafeDocumentRoot::check()` method in `glpi/src/common/Document/SafeDocumentRoot.php` the following code to disable the server root directory validation:
```php
        $this->validated = true;
        $this->validation_messages[] = __('Web server root directory configuration validation disabled by iService.');
        return;
```
- Update `validateValues()` method in `glpi/plugins/fields/inc/container.class.php` line 1387 to allow 'NULL' as number value:
```php
            } else if ($field['type'] == 'number' && !empty($value) && strtoupper($value) !== 'NULL' && !is_numeric($value)) {
```