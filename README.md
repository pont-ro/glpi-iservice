# Setup

See the setup instructions for the DEV, TEST and PROD environments [here](setup/README.md)

# Useful tips

- The glpi system uses [Tabler Icons](https://tabler-icons.io) and [Font Awesome](https://fontawesome.com) for icons. You can find the icon names in the websites.

# GLPI hacks

- Update: glpi/src/RichText/UserMention.php, line, handleUserMentions() method, line 80 to: `$previous_value = $item->fields[$content_field] ?? null`;
- If "Find menu is not working" update: 'glpi/src/Html.php', line 6664 to `if (is_array($menu) && strlen($menu['title'] ?? '') > 0) {`
