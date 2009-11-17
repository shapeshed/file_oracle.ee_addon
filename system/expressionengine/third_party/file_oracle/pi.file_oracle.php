<?php
/** 
 * ExpressionEngine
 *
 * LICENSE
 *
 * ExpressionEngine by EllisLab is copyrighted software
 * The licence agreement is available here http://expressionengine.com/docs/license.html
 * 
 * File Oracle
 * 
 * @category   Plugins
 * @package    File Oracle
 * @version    2.0.0
 * @since      0.1.0
 * @author     George Ornbo <george@shapeshed.com>
 * @see        {@link http://github.com/shapeshed/file_oracle.ee_addon/} 
 * @license    {@link http://opensource.org/licenses/bsd-license.php} 
 */

/**
* Plugin information used by ExpressionEngine
* @global array $plugin_info
*/
$plugin_info = array(
  'pi_name'         => 'File Oracle',
  'pi_version'      => '2.0.0',
  'pi_author'       => 'George Ornbo',
  'pi_author_url'   => 'http://shapeshed.com/',
  'pi_description'  => 'Provides information on a file',
  'pi_usage'        => File_oracle::usage()
);

class File_oracle{

  /**
  * Tag data holds the inital ExpressionEngine tag data.
  * It is then used as the array to hold parsed data and returned.
  * @var array
  */	
  private $tagdata = array();

  /**
  * Holds the path to the file to be evaluated
  * @see __construct
  * @var string
  */	
  private $file;

  /**
  * Data sent back to calling function
  * @var string
  */	
  public $return_data = "";

  /**
  * The error message used if no file is found
  * @var string
  */	
  public $error_message = "The file was not found - please check your settings";

  /**
  * Holds the response from the pathinfo() on the file
  * @var array
  */	
  private $pathinfo = array();	

  /**
  * Holds the response from the stat() on the file
  * @var array
  */	
  private $stat = array();	

  /**
  * Holds additional data on the file
  * @var array
  */	
  private $data = array();

  /**
  * ExpressionEngine needs this as it is PHP4 based so doesn't get __construct()
  * @access public
  */
  public function File_oracle()
  {
  	$this->__construct();
  }

  /**
  * Constructor class. Gets data from EE templates, checks the file exists.
  * If the file exists calls the get_file_data() function
  * @access public
  * @return array
  */
  public function __construct() 
  {
    $this->EE =& get_instance();
    
    $this->tagdata = $this->EE->TMPL->tagdata;
  

    $this->file = str_replace(SLASH, '/', $this->EE->TMPL->fetch_param('file'));
    
    $this->file = trim(strip_tags($this->file));

    if (stristr($this->file, $_SERVER['DOCUMENT_ROOT']))
    {
      $this->file =  $this->file;
    }
    else
    {
      $this->file = $_SERVER['DOCUMENT_ROOT'] . $this->file;		  
    }
  
    $this->return_data = file_exists($this->file) ? $this->get_file_data($this->file, $this->tagdata) : $this->error_message;
  }

  /**
  * Gets data and information on the file, parses it for templates and returns result
  * @access protected
  * @return array
  */		
  protected function get_file_data($file, $tagdata)
  {
    clearstatcache();

    $this->EE =& get_instance();
    
    $this->file = str_replace(SLASH, '/', $this->EE->TMPL->fetch_param('file'));
    $this->file = trim(strip_tags($this->file));
    
    if (stristr($this->file, $_SERVER['DOCUMENT_ROOT']))
    {
      $this->file =  $this->file;
    }
    else
    {
      $this->file = $_SERVER['DOCUMENT_ROOT'] . $this->file;		  
    }	
    
    if (!file_exists($this->file)) 
    {
      $this->return_data = $this->error_message;
      return;
    }

    $this->pathinfo = pathinfo($this->file);		
    $this->stat = stat($this->file);

    $this->data['human_size'] 	= $this->human_size($this->stat['size']);	
    $this->data['file_perms']	= substr(decoct(fileperms($this->file)),2);	
    $this->data['mime_type']	= $this->get_mime_type($this->file);	
    $this->data['md5']			= md5_file($this->file);
    $this->data['sha1']			= sha1_file($this->file);

    $date_vars = array('atime', 'mtime', 'ctime');

    foreach ($date_vars as $val)
    {
      if (preg_match_all("/".LD.$val."\s+format=[\"'](.*?)[\"']".RD."/s", $this->EE->TMPL->tagdata, $matches))
      {
        
        if (strpos($this->EE->TMPL->tagdata, LD.$val) === FALSE) continue;

        if (preg_match_all("/".LD.$val."\s+format=([\"'])([^\\1]*?)\\1".RD."/s", $this->EE->TMPL->tagdata, $matches))
        {
          for ($j = 0; $j < count($matches[0]); $j++)
          {
            $matches[0][$j] = str_replace(array(LD,RD), '', $matches[0][$j]);

            switch ($val)
            {
              case 'mtime' : $mtime[$matches[0][$j]] = $this->EE->localize->fetch_date_params($matches[2][$j]);
                break;
              case 'atime' : $atime[$matches[0][$j]] = $this->EE->localize->fetch_date_params($matches[2][$j]);
                break;
              case 'ctime' : $ctime[$matches[0][$j]] = $this->EE->localize->fetch_date_params($matches[2][$j]);
                break;
            }
          }     
        }
      }
    }
  foreach ($this->EE->TMPL->var_single as $key => $val)
  {
    if (isset($this->stat[$val]))
    {
      $tagdata = $this->EE->TMPL->swap_var_single($key, $this->stat[$val], $tagdata);
    }
    if (isset($this->pathinfo[$val]))
    {
      $tagdata = $this->EE->TMPL->swap_var_single($key, $this->pathinfo[$val], $tagdata);
    }
    if (isset($this->data[$val]))
    {
      $tagdata = $this->EE->TMPL->swap_var_single($key, $this->data[$val], $tagdata);
    }
    if (isset($mtime[$key]))
    {
      foreach ($mtime[$key] as $dvar)
        $val = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $this->stat['mtime'], TRUE), $val);
        $tagdata = $this->EE->TMPL->swap_var_single($key, $val, $tagdata);         
    }
    if (isset($atime[$key]))
    {
      foreach ($atime[$key] as $dvar)
        $val = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $this->stat['atime'], TRUE), $val);
        $tagdata = $this->EE->TMPL->swap_var_single($key, $val, $tagdata);         
    }
    if (isset($ctime[$key]))
    {
      foreach ($ctime[$key] as $dvar)
      
        $val = str_replace($dvar, $this->EE->localize->convert_timestamp($dvar, $this->stat['ctime'], TRUE), $val);
        $tagdata = $this->EE->TMPL->swap_var_single($key, $val, $tagdata);
    }
  }

  return $tagdata; 	

  }
  	
  /**
  * Formats the byte size of a file into a human readable format
  * @access private
  * @return string
  */	
  private function human_size($bytesize) 	
  {
  $size = $bytesize / 1024;

  if($size < 1024)
  {
    $size = number_format($size, 0);
    $size .= 'KB';
  } 
  else 
  {
    if($size / 1024 < 1024) 
    {
      $size = number_format($size / 1024, 0);
      $size .= 'MB';
    } 
    else if ($size / 1024 / 1024 < 1024)  
    {
      $size = number_format($size / 1024 / 1024, 0);
      $size .= 'GB';
    } 	
  }
  return $size;
  }

  /**
  * Gets the mime type of the file
  * @access private
  * @return string
  */	
  private function get_mime_type($file_path)
  {
  $mtype = '';
    if (function_exists('mime_content_type'))
    {
      $mtype = mime_content_type($file_path);
    }
    else if (function_exists('finfo_file'))
    {
      $finfo = finfo_open(FILEINFO_MIME);
      $mtype = finfo_file($finfo, $file_path);
      finfo_close($finfo);  
    }
    if ($mtype == '')
    {
      $mtype = "application/force-download";
    }
  return $mtype;
  }

  /**
  * Plugin usage documentation
  *
  * @return	string Plugin usage instructions
  */
  public function usage()
  {
    return "Documentation is available here http://shapeshed.github.com/expressionengine/plugins/file_oracle/";
  }
	
}

?>