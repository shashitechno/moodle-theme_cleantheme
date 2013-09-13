moodle-theme_cleantheme shows how the results evaluted from course search plugin are displayed. It is based on bootstrap
clean theme.

**** To enable "advance course search" ****

you may install search_cleantheme.

OR 

follow these steps.

Step 1 Either copy/replace file renderer.php file to your theme renderer.php file.

---> * replace /theme/cleantheme/renderer.php with /theme/yourtheme/renderer.php

---> * The standard theme doesn't have renderer file so you need to simply copy the renderer file.

Step 2 rename renderer class name acording to your theme name.

for example if you are using theme 'clean' then rename the class names to 'theme_clean_core_renderer' & 'theme_clean_core_course_renderer'.

Thanks :)
