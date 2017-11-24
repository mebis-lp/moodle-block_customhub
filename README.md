Hub search plugin
-----------------

This plugin allows to search custom hubs in Moodle 3.4.1 and above.

Functionality to register and search on custom hubs was present in Moodle 3.3 but was removed in 
Moodle 3.4

* Install required plugin tool_customhub from https://github.com/moodlehq/moodle-tool_customhub , 
  register on custom hub as described there
* Place the source code of this plugin into blocks/customhub
* Complete installation in CLI or on the website
* Add instances of the block "Custom hub search" wherever needed. You may want to replace
  instances of block "Community finder" with this block because the functionality is similar
* Click "Search" inside the block, select custom hub in the hub selector and search for courses there

Capabilities 'block/customhub:addcommunity' and 'block/customhub:downloadcommunity' control who can
add courses to the block and download courses.

These two plugins can be found on moodlehq github, however Moodle HQ will not actively support them.
Pull requests will be reviewed and merged.
