<?php

include('config.php');

$file = null;
if(!empty($_GET['f']))
{
  $file = preg_replace('#[\\\/]+#is', DIRECTORY_SEPARATOR, $_GET['f']).'.sql';
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <title></title>
  </head>
  <body>
    <?php if(empty($file)) : ?>
      NO FILE SET!
    <?php elseif(!file_exists($sqldir.DIRECTORY_SEPARATOR.$file)) : ?>
      FILE NOT FOUND
    <?php else : ?>
      <textarea id="ta" onfocus="this.select()"><?php readfile($sqldir.DIRECTORY_SEPARATOR.$file); ?></textarea>
    <?php endif; ?>
    <script type="text/javascript">
      function viewportSize() {
        if(typeof(window.innerHeight) != 'undefined') {
          return { width: window.innerWidth, height: window.innerHeight };
        } else if(typeof(document.documentElement) != 'undefined' && typeof(document.documentElement.clientHeight) != 'undefined' && document.documentElement.clientHeight != 0) {
          return { width: document.documentElement.clientWidth, height: document.documentElement.clientHeight };
        } else {
          return { width: document.getElementsByTagName('body')[0].clientWidth, height: document.getElementsByTagName('body')[0].clientHeight };
        }
      }
      
      var ta = document.getElementById('ta');
      var vp_size = viewportSize();
      console.log(vp_size);
      ta.style.width = (vp_size.width-30)+'px';
      ta.style.height = (vp_size.height-30)+'px';
      ta.focus();
    </script>
  </body>
</html>
