<?php

class sql 
{
  protected static $messages = array();
  
  protected $global_collation = 'utf8_czech_ci';
  
  protected $implemented_types = array(
    'int', 'tinyint', 'bigint', 'float', 'double', 'char', 'varchar', 'text', 'mediumtext', 'longtext', 'date', 'time', 'datetime', 'timestamp', 'blob'
  ); 

  public function generateSql($data)
  {
    $sql = array();
    foreach($data as $table_name => $table_specs)
    {
      if($table_name == '_collation' && is_string($table_specs))
      {
        $this->global_collation = $table_specs;
        continue;
      }
      
      $table_sql = $this->generateTableSql($table_name, $table_specs);
      if($table_sql)
      {
        $sql[] = $this->generateTableComment($table_name);
        $sql[] = $table_sql;
      }
    }
    return join("\r\n\r\n", array_map('trim', $sql));
  }
  
  public function generateTableSql($table_name, $specs)
  {
    $table = new sql_table($table_name);
    
    $valid = true;
    
    $table->setCollation(empty($specs['_collation'])?$this->global_collation:$specs['_collation']);
    
    foreach($specs as $spec_name => $data)
    {
      $spec_valid = true;
        
      if($spec_name == '_indexes')
      {
        foreach($data as $index_name => $column_names)
        {
          $table->addIndex($index_name, $column_names);
        }
      }
      elseif($spec_name == '_uniques')
      {
        foreach($data as $index_name => $column_names)
        {
          $table->addUnique($index_name, $column_names);
        }
      }
      elseif(substr($spec_name, 0, 1)=='_')
      {
        continue;
      }
      else
      {
        if($spec_name == 'id' && empty($data))
        {
          $data = array(
            'type' => 'integer',
            'primaryKey' => true,
            'autoincrement' => true,
            'null' => false
          );
        }
        
        if(!empty($data['primaryKey']))
        {
          $table->addPK($spec_name);
        }
        
        if(!empty($data['autoincrement']))
        {
          $table->setAutoincrement($spec_name);
        }
        
        if(!empty($data['foreignKey']))
        {
          $foreign_table = empty($data['foreignTable'])?null:$data['foreignTable'];
          $foreign_col_name = empty($data['foreignReference'])?null:$data['foreignReference'];
          $ondelete = empty($data['ondelete'])?null:$data['ondelete'];
          
          $fk_valid = true;
          if(empty($foreign_table))
          {
            sql::error($table_name.'/'.$spec_name.': no foreignTable set');
            $fk_valid = false;
          }
          if(empty($foreign_col_name))
          {
            $foreign_col_name = $spec_name;
          }
          
          if($fk_valid)
          {
            $table->addFK($spec_name, $foreign_table, $foreign_col_name, $ondelete);
            $table->addIndex($spec_name.'_fk', array($spec_name));
          }
        }
        
        $type = empty($data['type'])?null:$data['type'];
        $size = empty($data['size'])?null:$data['size'];
        $size .= empty($data['precision'])?'':','.$data['precision'];
        $null = !isset($data['null'])?true:(boolean)$data['null'];
        $default = empty($data['default'])?null:$data['default'];
        $collation = empty($data['collation'])?$table->getCollation():$data['collation'];
        
        if(empty($type))
        {
          sql::error($table_name.'/'.$spec_name.': no type set');
          $spec_valid = false;
        }
        elseif(preg_match('#^(.*?)\((\d+(,\d+)?)\)$#is', $type, $m))
        {
          $type = $m[1];
          if(!empty($size))
          {
            sql::warning($table_name.'/'.$spec_name.': double size set');
          }
          else
          {
            $size = $m[2];
          }
        }
        
        $type = $this->normalizeType($type);
        if(!in_array($type, $this->implemented_types))
        {
          sql::error($table_name.'/'.$spec_name.': column type '.$type.' not yet implemented');
          $spec_valid = false;
        }
        
        if(empty($size))
        {
          $size = $this->getDefaultTypeSize($type);
        }
        
        if($spec_valid)
        {
          $table->addColumn($spec_name, $type, $size, $null, $default, $collation);
        }
      }
      
      $valid = $valid && $spec_valid;
    }
    
    if($valid)
    {
      return $table->generateSql();
    }
    return false;
  }
  
  public function normalizeType($type)
  {
    $renames = array(
      'integer' => 'int',
      'longvarchar' => 'text'
    );
    return isset($renames[$type])?$renames[$type]:$type;
  }
  
  public function getDefaultTypeSize($type)
  {
    $sizes = array(
      'int' => 11,
      'varchar' => 127  
    );
    return isset($sizes[$type])?$sizes[$type]:null;
  }
  
  public function generateTableComment($table_name)
  {
    return '# '.$table_name;
  }
  
  public static function message($text, $type)
  {
    self::$messages[$type][] = $text;
  }
  
  public static function info($text)
  {
    return self::message($text, 'info');
  }
  
    
  public static function success($text)
  {
    return self::message($text, 'success');
  }
  
    
  public static function warning($text)
  {
    return self::message($text, 'warning');
  }
  
    
  public static function error($text)
  {
    return self::message($text, 'error');
  }
  
  public static function getMessages()
  {
    return self::$messages;
  }
}

class sql_table
{
  protected $table_name = null;
  protected $columns = array();
  protected $pks = array();
  protected $fks = array();
  protected $indexes = array();
  protected $uniques = array();
  protected $autoincrement = null;
  protected $collation = null;
  
  public function __construct($table_name)
  {
    $this->table_name = $table_name;
  }
  
  public function addColumn($name, $type, $size, $null, $default, $collation)
  {
    $this->columns[$name] = array(
      'type' => $type, 
      'size' => $size, 
      'null' => $null, 
      'default' => $default, 
      'collation' => $this->typeHasCollation($type)?$collation:null
    );
  }
  
  public function addPK($col_name)
  {
    $this->pks[$col_name] = $col_name;
  }
  
  public function addFK($col_name, $foreign_table, $foreign_col_name, $ondelete)
  {
    $this->fks[] = array(
      'column' => $col_name,
      'table' => $foreign_table,
      'f_column' => $foreign_col_name,
      'ondelete' => $ondelete
    );
  }
  
  public function addIndex($name, $columns)
  {
    $this->indexes[$name] = $columns;
  }
  
  public function addUnique($name, $columns)
  {
    $this->uniques[$name] = $columns;
  }
  
  public function setAutoincrement($col_name)
  {
    $this->autoincrement = $col_name;
  }
  
  public function setCollation($collation)
  {
    $this->collation = $collation;
  }
  
  public function getCollation()
  {
    return $this->collation;
  }
  
  public function generateSql()
  {
    $sql = "
DROP TABLE IF EXISTS `".$this->table_name."`;

CREATE TABLE `".$this->table_name."` (
  ".join(",\r\n  ", $this->generateColumnsSql()).",
  PRIMARY KEY (`".join("`, `", $this->pks)."`)".(!empty($this->indexes) || !empty($this->uniques)?",":"")."
  ".join(",\r\n  ", $this->generateKeysSql())."
) ENGINE=MyISAM DEFAULT CHARSET=utf8".($this->collation?" COLLATE=".$this->collation:"").";
";
    return trim($sql);
  }
  
  public function generateColumnsSql()
  {
    $sql = array();
    foreach($this->columns as $col_name => $data)
    {
      $sql[] = "`".$col_name."` "
        .$data['type'].(!empty($data['size'])?"(".$data['size'].")":"")
        .($data['null']?" ":" NOT ")."NULL"
        .($this->autoincrement==$col_name?" AUTO_INCREMENT":"")
        .(!empty($data['collation'])?" COLLATE ".$data['collation']:"")
        .($this->autoincrement==$col_name?"":" DEFAULT ".$this->formatDefaultValue($data['type'], $data['default'], $data['null']))
      ;
    }
    return $sql;
  }
  
  public function generateKeysSql()
  {
    $sql = array();
    foreach($this->indexes as $name => $columns)
    {
      $sql[] = "KEY `".$name."` (`".join("`, `", $columns)."`)";
    }
    foreach($this->uniques as $name => $columns)
    {
      $sql[] = "UNIQUE KEY `".$name."` (`".join("`, `", $columns)."`)";
    }
    return $sql;
  }
  
  public function formatDefaultValue($type, $value, $null)
  {
    if($null && $value===null) return 'NULL';
    switch($type)
    {
      case 'int': 
      case 'tinyint':
        return intval($value); 
        break;
      case 'float':
      case 'double':
      case 'char':
      case 'varchar':
      case 'text':
      case 'mediumtext':
      case 'longtext':
      case 'date':
      case 'time':
      case 'datetime':
      case 'timestamp':
        return "'".(string)$value."'";
        break;
      default:
        return "'".(string)$value."'";
        break;
    }
  }
  
  public function typeHasCollation($type)
  {
    return in_array($type, array(
      'char', 
      'varchar',
      'text',
      'mediumtext',
      'longtext'
    ));
  }
}