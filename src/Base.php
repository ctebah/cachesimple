<?php
namespace Xrt\CacheSimple;

/**
 * Apstraktna klasa cache_simple koja je zamisljena da je druge klase nasledjuju i implementiraju metode za
 * proveru, citanje i snimanje u kes
 *
 */
abstract class Base {

    /**
     *
     * Asocijativni niz opcija za kes. Opcije koje se mogu postaviti su
     * int lock_lifetime, maximalno trajanje locka, global
     * boolean automatic_serialization, default: true, global
     * string root_dir root direktorijum u kome se cache nalazi, koristi se u cache_simple_file
     * int directory_level nivo do kog treba ici u pravljenju poddirektorijuma, koristi se u cache_simple_file
     * int chmod_dir, kad kreira dir, koji chmod da mu stavi, koristi se u cache_simple_file
     * int chmod_file, kad kreira fajl, koji chmod da mu stavi, koristi se u cache_simple_file
     * string salt, zacin kad se kreira id, koristi se u cache_simple_htmloutput_file
     *
     * @var array
     */
    protected $options;
    
    
    const LIFETIME_FOREVER = 999999;
    const LIFETIME_24H = 86400;
    const LIFETIME_1H = 3600;
    const LIFETIME_30M = 1800;
    
    public function __construct($options = array()) {
        if (!isset($options['automatic_serialization']))        $options['automatic_serialization'] = true;
        if (!isset($options['lock_lifetime']))                  $options['lock_lifetime'] = 15;
        $this->options = $options;
    }
    
    /**
     * za zadati id proverava da li postoji validan kesirani podatak, vraca se true za validan podatak, false za nevalidan
     * pseudo-abstract - we allow signature to be redefined
     *
     * @param string $id
     * @return boolean
     */
    public function check($id) { }
    
    /**
     * Brise podatak iz kesa
     *
     * @param string $id
     * @return boolean
     */
    abstract public function delete($id);
    
    /**
     * Vraća keširani sadržaj ili null, ako nema sadržaja
     *
     * @param string $id
     * @return mixed
     */
    abstract public function get($id);
    
    /**
     *
     * @return array
     */
    public function getOptions() {
        return $this->options;
    }
    
    /**
     * Da li je podatak lockovan. Lockovan je podatak koji se upravo generise i bice zapisan u bliskoj buducnosti.
     * Strategija je da vratimo podatak koji imamo od ranije, dok je lockovan.
     *
     * @param $id
     * @return boolean
     */
    abstract function locked($id);
    
    /**
     * Kreira lock za prosledjeni $id, da ne bismo dosli u utrku ko zapisuje fajl
     *
     * @param string $id
     * @return mixed
     */
    abstract function lock_obtain($id);
    
    /**
     * Ukida dobijeni lock
     *
     * @param $id
     */
    abstract function lock_release($id);
    
    /**
     * Snima u keš podatak koji mu zadamo i oslobadja lock. Ovo je wrapper funkcija za sam upis
     * (metod _put()) i oslobadjanje locka, u principu ne bi trebalo da se override-uje u klasama
     * naslednicama.
     *
     * @param string $id
     * @param mixed $value
     * @param int $lifetime dužina trajanja podatka u kešu
     */
    public function put($id, $value, $lifetime) {
        $this->_put($id, $value, $lifetime);
        $this->lock_release($id);
    }
    
    protected function _put($id, $value, $lifetime) { }
}
    
    