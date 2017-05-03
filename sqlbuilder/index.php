<?php

include('config.php');

require_once('sql.class.php');
require_once('Spyc.class.php');

$messages = array();

if(!empty($_POST) && !empty($_POST['filename']))
{
  $yml_file = $datadir.'/'.$_POST['filename'].'.yml';
  if(!file_exists($yml_file))
  {
    $messages['error'][] = 'Soubor '.$yml_file.' neexistuje';
  }
  else
  {
    $data = Spyc::YAMLLoad($yml_file);
    if(count($data)==1 && isset($data['propel']))
    {
      $data = $data['propel'];
    }
    
    $generator = new sql();
    $sql = $generator->generateSql($data);
    $generate_messages = $generator->getMessages();
    foreach($generate_messages as $type => $type_msgs)
    {
      $messages[$type] = array_merge(isset($messages[$type])?$messages[$type]:array(), $type_msgs);
    }
    
    $sql_filename = $_POST['filename'];
    $sql_file_path = $sqldir.'/'.$sql_filename.'.sql';
    if(!file_exists(dirname($sql_file_path)))
    {
      mkdir(dirname($sql_file_path), 0777, true);
    }
    file_put_contents($sql_file_path, $sql);
    
    $messages['success'][] = 'Soubor <a href="sqlfile.php?f='.urlencode($sql_filename).'">'.$sql_filename.'.sql</a> vytvořen';
  }
}

function find_files($ext, $dir, $path = '')
{
  $files = scandir($dir);
  $yml_files = array();
  foreach($files as $file)
  {
    if($file == '.' || $file == '..') continue;
    if(pathinfo($file, PATHINFO_EXTENSION)==$ext)
    {
      $yml_files[$path.pathinfo($file, PATHINFO_FILENAME)] = $path.$file;
    }
    elseif(is_dir($dir.DIRECTORY_SEPARATOR.$file))
    {
      $yml_files = array_merge($yml_files, find_files($ext, $dir.DIRECTORY_SEPARATOR.$file, $path.$file.'/'));
    }
  }
  return $yml_files;
}

$yml_files = find_files('yml', $datadir);

$sql_files = find_files('sql', $sqldir);

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8">
  <title>SQL generator</title>
  </head>
  <body>
    <div id="messages">
      <?php foreach($messages as $type => $msgs) : ?>
        <?php foreach($msgs as $msg) : ?>
          <div class="message <?php echo $type; ?>"><?php echo $msg; ?></div>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
    <?php foreach($yml_files as $filename => $file) : ?>
      <form action="" method="POST">
        <?php echo $file; ?>
        <input type="hidden" name="filename" value="<?php echo $filename; ?>" />
        <input type="submit" value="generovat" />
        <?php if(!empty($sql_files[$filename])) : ?>
          <a href="sqlfile.php?f=<?php echo urlencode($filename); ?>" target="_blank"><?php echo $sql_files[$filename]; ?></a>
        <?php endif; ?>
      </form>
    <?php endforeach; ?>
  </body>
</html>
