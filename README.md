# Setup

See the setup instructions for the DEV, TEST and PROD environments [here](setup/README.md)

# Useful tips

- The glpi system uses [Tabler Icons](https://tabler-icons.io) and [Font Awesome](https://fontawesome.com) for icons. You can find the icon names in the websites.

# How to install a new GLPI version

- Download the latest package from the [GLPI Download page](https://glpi-project.org/downloads/).
- Rename the `glpi` folder to `glpi-old` and create a new (empty) `glpi` folder.
- Unpack the latest package to this new (empty) `glpi` folder.
- Copy from the `gli-old` folder to the new `glpi` folder the following:
  - the contents of the `config` folder
  - the contents of the `files\_plugins` folder
  - the contents of the `plugins` folder (be aware that the `iservice` folder is a symlink created with `mklink /D iservice "..\..\plugin"` or `ln -s ../../plugin glpi/plugins/iservice`).
- Navigate to the root URL and perform the GLPI upgrade steps
- Perform the following GLPI hacks

## GLPI hacks

- Update `handleUserMentions()` method in `glpi/src/RichText/UserMention.php` line 80
  - from: `$previous_value = $item->fields[$content_field];` 
  - to: `$previous_value = $item->fields[$content_field] ?? null;`
- OBSOLETE: If "Find menu is not working" update `glpi/src/Html.php` line 6664
  - from `if (strlen($menu['title']) > 0) {`
  - to: `if (is_array($menu) && strlen($menu['title'] ?? '') > 0) {`
- OBSOLETE: Add at the beginning of the `SafeDocumentRoot::check()` method in `glpi/src/common/Document/SafeDocumentRoot.php` the following code to disable the server root directory validation:
```php
        $this->validated = true;
        $this->validation_messages[] = __('Web server root directory configuration validation disabled by iService.');
        return;
```
- OBSOLETE: Update `validateValues()` method in `glpi/plugins/fields/inc/container.class.php` line 1387 to allow 'NULL' as number value:
```php
            } else if ($field['type'] == 'number' && !empty($value) && strtoupper($value) !== 'NULL' && !is_numeric($value)) {
```

- removed custom select2 matcher from 'glpi/src/Html.php' line 4691, method: jsAdaptDropdown
  - Removed code:
```matcher: function(params, data) {
               // store last search in the global var
               query = params;

               // If there are no search terms, return all of the data
               if ($.trim(params.term) === '') {
                  return data;
               }

               var searched_term = getTextWithoutDiacriticalMarks(params.term);
               var data_text = typeof(data.text) === 'string'
                  ? getTextWithoutDiacriticalMarks(data.text)
                  : '';
               var select2_fuzzy_opts = {
                  pre: '<span class=\"select2-rendered__match\">',
                  post: '</span>',
               };

               if (data_text.indexOf('>') !== -1 || data_text.indexOf('<') !== -1) {
                  // escape text, if it contains chevrons (can already be escaped prior to this point :/)
                  data_text = jQuery.fn.select2.defaults.defaults.escapeMarkup(data_text);
               }

               // Skip if there is no 'children' property
               if (typeof data.children === 'undefined') {
                  var match  = fuzzy.match(searched_term, data_text, select2_fuzzy_opts);
                  if (match == null) {
                     return false;
                  }
                  data.rendered_text = match.rendered_text;
                  data.score = match.score;
                  return data;
               }

               // `data.children` contains the actual options that we are matching against
               // also check in `data.text` (optgroup title)
               var filteredChildren = [];

               $.each(data.children, function (idx, child) {
                  var child_text = typeof(child.text) === 'string'
                     ? getTextWithoutDiacriticalMarks(child.text)
                     : '';

                  if (child_text.indexOf('>') !== -1 || child_text.indexOf('<') !== -1) {
                     // escape text, if it contains chevrons (can already be escaped prior to this point :/)
                     child_text = jQuery.fn.select2.defaults.defaults.escapeMarkup(child_text);
                  }

                  var match_child = fuzzy.match(searched_term, child_text, select2_fuzzy_opts);
                  var match_text  = fuzzy.match(searched_term, data_text, select2_fuzzy_opts);
                  if (match_child !== null || match_text !== null) {
                     if (match_text !== null) {
                        data.score         = match_text.score;
                        data.rendered_text = match_text.rendered;
                     }

                     if (match_child !== null) {
                        child.score         = match_child.score;
                        child.rendered_text = match_child.rendered;
                     }
                     filteredChildren.push(child);
                  }
               });

               // If we matched any of the group's children, then set the matched children on the group
               // and return the group object
               if (filteredChildren.length) {
                  var modifiedData = $.extend({}, data, true);
                  modifiedData.children = filteredChildren;

                  // You can return modified objects from here
                  // This includes matching the `children` how you want in nested data sets
                  return modifiedData;
               }

               // Return `null` if the term should not be displayed
               return null;
            },
```
