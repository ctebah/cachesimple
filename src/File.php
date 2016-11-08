<?php
namespace Xrt\CacheSimple;

/**
 *
 * Kesiranje se snima u fajl, id/value i lifetime se eksplicitno zadaju
 */
class File extends Base {
    protected $_filename, $_touch_filename;
    private static $_default_root_dir = '/tmp/';
    
    protected $__filenames = array();
    
    public function __construct($options = array()) {
        if (!isset($options['root_dir']))               $options['root_dir'] = self::$_default_root_dir;
        if (!isset($options['root_subdir']))            $options['root_subdir'] = '';
        if (!isset($options['directory_level']))        $options['directory_level'] = 2;
        if (!isset($options['directory_name_length']))  $options['directory_name_length'] = 2;
        if (!isset($options['chmod_dir']))              $options['chmod_dir'] = 0775;
        if (!isset($options['chmod_file']))             $options['chmod_file'] = 0775;
        
        $options['root_dir'] .= $options['root_subdir'];

        if (!isset($GLOBALS['webcache_dir'])) {
        	$GLOBALS['webcache_dir'] = '/tmp';
        }
        
        parent::__construct($options);
    }

    public function check($id) {
        return (@file_exists($this->_filename($id)) && $this->locked($id)) ||
            (@filemtime($this->_filename($id)) > time());
    }

    public function clear() {
        if (strcmp($this->options['root_dir'], self::$_default_root_dir)) { // wtf?
            if (file_exists($this->options['root_dir'])) {
                @mkdir($GLOBALS['webcache_dir'] . '/recycler/', 0775, true);
                rename($this->options['root_dir'], $GLOBALS['webcache_dir'] . '/recycler/' . uniqid());
            }
        }
    }

    public function delete($id) {
        // namerno ostavljamo lock! mozda neko upravo generise novi sadrzaj za taj cache entry
        return unlink($this->_filename($id));
    }

    public function delete_all($id) {
        foreach (glob($this->_filename($id, false) . "*", GLOB_NOSORT) as $fn) {
            @unlink($fn);
        }
    }

    public function get($id) {
        if ($this->options['automatic_serialization']) return unserialize(@file_get_contents($this->_filename($id)));
        else return @file_get_contents($this->_filename($id));
    }

    public function invalidate($id) {
        return file_exists($fn = $this->_filename($id)) ? touch($fn, time() - 3600) : true;
    }

    public function locked($id) {
        return @filemtime($this->_filename($id) . ".lock") > (time() - $this->options['lock_lifetime']);
    }

    public function lock_obtain($id) {
        register_shutdown_function(array($this, 'lock_release'), $id);
        return @touch($this->_filename($id) . ".lock");
    }

    public function lock_release($id) {
        @unlink($this->_touch_filename($id));
        return @unlink($this->_filename($id) . ".lock");
    }

    public function put($id, $value, $lifetime) {
        if ($this->options['automatic_serialization']) $this->_safe_write($this->_filename($id), serialize($value), $this->options['chmod_file']);
        else $this->_safe_write($this->_filename($id), $value, $this->options['chmod_file']);
        touch($this->_filename($id), time() + $lifetime);
    }

    public function _filename($id) {
    	if (isset($this->__filenames[$id])) return $this->__filenames[$id];
    	if (strlen($id) < ($this->options['directory_name_length'] * $this->options['directory_level'])) {
    		$id = str_pad($id, $this->options['directory_name_length'] * $this->options['directory_level'], "0");
    	}
    	$dir = $this->options['directory_level'] ? join('/', array_slice(preg_split("/(.{{$this->options['directory_name_length']}})/", $id, $this->options['directory_level'] + 1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY), 0, $this->options['directory_level'])) . '/' : '';
        $this->__my_mkdir_recursive($this->options['root_dir'], $dir, $this->options['chmod_dir']);
        return $this->__filenames[$id] = $this->options['root_dir'] . "$dir$id.cache.html";
    }

    public function _touch_filename($id) {
        if (!$this->_touch_filename) {
            $fn = $this->_filename($id);
            $this->_touch_filename = dirname($fn) . '/touched.' . basename($fn);
        }
        return $this->_touch_filename;
    }

    public function _safe_write($fn, $content) {
        $tempfile = @tempnam(dirname($fn), 'tmp_');
        $fp = @fopen($tempfile, 'wb+'); @fwrite($fp, $content); @fclose($fp);
        $succ = @rename($tempfile, $fn);
        @chmod($fn, $this->options['chmod_file']);
        if (!$succ) {
            @copy($tempfile, $fn);
            @unlink($tempfile);
        }
    }

    public function __my_mkdir_recursive($prepend_path, $path, $mode) {
        $old = umask(0);
        @mkdir($prepend_path . $path, $mode, true);
        umask($old);
    }
}