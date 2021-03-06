<?php
namespace Qing\Lib\Translator;

use Qing\Lib\Exception;

class Gettext extends Adapter
{

    /**
     *
     * @var string
     */
    protected $_defaultDomain;

    /**
     *
     * @var string
     */
    protected $_locale;

    /**
     *
     * @var int
     */
    protected $_category;

    /**
     *
     * @var string|array
     */
    protected $_directory;
    /**
     * 当不同域但待译内容一样时，后面加入的domain是否要盖掉前面domain的翻译内容
     * @var boolean 默认true
     */
    protected $_override;
    public function __construct(array $options)
    {
        parent::__construct($options);
        $this->prepareOptions($options);
    }

    protected function getOptionsDefault()
    {
        return array(
            "category" => LC_ALL,
            "defaultDomain" => "message",
            "override"=>'true',
            "cache"=>null
        );
    }

    /**
     * Returns the translation related to the given key
     *
     * @param
     *            string index
     * @param
     *            array placeholders
     * @param
     *            string domain
     * @return string
     */
    public function query($index, $placeholders = null, $domain = null)
    {
        $container['args'] = func_get_args();
        $container['locale'] = $this->_locale;
        $j = '_';
        $cacheId   = $container['locale'].$j.md5(serialize($container));
        if($this->_cacheEngine){
            $cacheData = $this->_cacheEngine->get($cacheId);
            if($cacheData){
                return $cacheData;
            }
        }
        if (! $domain) {
            if(!is_array($this->_directory))
                $translation = gettext($index);
            else{
                $isFounded = false;
                foreach($this->_directory as $d=>$dir){
                    if($this->exists($index,$d)){
                        $translation = dgettext($d, $index);
                        $isFounded   = true;
                        if(!$this->_override){
                            break;
                        }
                    }
                }
                if(!$isFounded){
                    $translation = gettext($index);
                }
            }
        } else {
            $translation = dgettext($domain, $index);
        }
        $data = $this->replacePlaceholders($translation, $placeholders);
        if($this->_cacheEngine){
            $cacheData = $this->_cacheEngine->set($cacheId,$data,600);
        }
        return $data;
    }

    /**
     * Replaces placeholders by the values passed
     */
    protected function replacePlaceholders($translation, $placeholders = null)
    {
        if (is_array($placeholders) && sizeof($placeholders) > 0) {
            foreach ($placeholders as $k => $v) {
                $translation = str_replace("%" . $k . "%", $v, $translation);
            }
        }
        return $translation;
    }

    /**
     * Check whether is defined a translation key in the internal array
     */
    public function exists($index,$domain=null)
    {
        return $this->query($index,null,$domain) != $index;
    }

    /**
     * The plural version of gettext().
     * Some languages have more than one form for plural messages dependent on the count.
     *
     * @param
     *            string msgid1
     * @param
     *            string msgid2
     * @param
     *            int count
     * @param
     *            array placeholders
     * @param
     *            string domain
     *
     * @return string
     */
    public function nquery($msgid1, $msgid2, $count, $placeholders = null, $domain = null)
    {
        $container['args'] = func_get_args();
        $container['locale'] = $this->_locale;
        //$cacheId   = md5(serialize($container));
        $j = PHP_OS=='Linux'?':':'-';
        $cacheId   = $container['locale'].$j.md5(serialize($container));
        if($this->_cacheEngine){
            $cacheData = $this->_cacheEngine->get($cacheId);
            if($cacheData){
                return $cacheData;
            }
        }
        if (! $domain) {
            if(!is_array($this->_directory))
                $translation = ngettext($msgid1, $msgid2, $count);
            else{
                $isFounded = false;
                foreach($this->_directory as $domain=>$dir){
                    if($this->exists($msgid1,$domain)){
                        $translation = dngettext($domain, $msgid1, $msgid2, $count);
                        $isFounded   = true;
                        if(!$this->_override)
                            break;
                    }
                }
                if(!$isFounded){
                    $translation = ngettext($msgid1, $msgid2, $count);
                }
            }
        } else {
            $translation = dngettext($domain, $msgid1, $msgid2, $count);
        }
        $data = $this->replacePlaceholders($translation, $placeholders);
        if($this->_cacheEngine){
            $cacheData = $this->_cacheEngine->set($cacheId,$data);
        }
        return $data;
    }

    /**
     * Changes the current domain (i.e.
     * the translation file). The passed domain must be one
     * of those passed to the constructor.
     *
     * @param
     *            string domain
     *
     * @return string Returns the new current domain.
     * @throws \InvalidArgumentException
     */
    public function setDomain($domain)
    {
        //        if($this->_cacheEngine){
        //            $key = $this->_cacheEngine->get('lang-'.md5($domain).date('YmdHi'));
        //            if(!$key){
        //                $this->_cacheEngine->set('lang-'.md5($domain),$domain,80);
        //                $domain = $key;
        //            }
        //        }
        return textdomain($domain);
    }

    /**
     * Sets the default domain
     *
     * @return string Returns the new current domain.
     */
    public function resetDomain()
    {
        return textdomain($this->getDefaultDomain());
    }

    /**
     * Sets the domain default to search within when calls are made to gettext()
     */
    public function setDefaultDomain($domain)
    {
        $this->_defaultDomain = $domain;
    }
    public function setOverride($o){
        $this->_override = $o;
    }
    /**
     * Gets the default domain
     */
    public function getDefaultDomain()
    {
        return $this->_defaultDomain;
    }

    /**
     * Sets the path for a domain
     */
    public function setDirectory($directory)
    {
        $this->_directory = $directory;
        if (is_array($this->_directory) && sizeof($this->_directory) > 0) {
            foreach ($this->_directory as $key => $value) {
                //gettext出现了缓存问题，需要借助缓存系统生成动态的domain
                $domain =  $key;
                $cacheKey    = 'lang-'.md5($domain).date('YmdHi');
                $path = realpath($value);
                //                if($this->_cacheEngine){
                //                    $target = $this->_cacheEngine->get($cacheKey);
                //                    if(!$target){
                //                        $target = QING_TMP_PATH.'/'.$cacheKey.'/'.$this->_locale.'/'.$domain.'.mo';
                //                        //mkdir(QING_TMP_PATH.'/'.$cacheKey,0777,true);
                //                        //copy($path.DIRECTORY_SEPARATOR.$this->_locale.DIRECTORY_SEPARATOR.$domain.'.mo',$target);
                //                        //$this->_cacheEngine->set($cacheKey,$target,80);
                //                        //$domain = $key;
                //                    }
                //                    //$path = $target;
                //                }
                bindtextdomain($domain, $path);
                textdomain($domain);
            }
        } else {
            bindtextdomain($this->getDefaultDomain(), $this->_directory);
            textdomain($this->getDefaultDomain());
        }
    }

    /**
     * 增加解析目录
     * @param $directoryKey
     * @param $path
     */
    public function addDirectory($directoryKey,$path){
        bindtextdomain($directoryKey, realpath($path));
        textdomain($directoryKey);
        $this->_directory[$directoryKey] = $path;
    }

    /**
     * Gets the path for a domain
     */
    public function getDirectory()
    {
        return $this->_directory;
    }

    /**
     * Sets locale information
     */
    public function setLocale($category, $locale,$charset='')
    {
        call_user_func_array("setlocale", func_get_args());
        $this->_locale = $locale.($charset?('.'.$charset):'');
        $this->_category = $category;
        // Windowns
        putenv("LC_ALL=" . $locale);
        putenv("LC_MESSAGES=$this->_locale");
        setlocale(LC_MESSAGES, $this->_locale);
        putenv("LANGUAGE=$this->_locale");
        putenv("LANG=$locale");
        // Linux
        $s = setlocale(LC_ALL, $this->_locale);
        return $this->_locale;
    }

    /**
     * Gets locale
     */
    public function getLocale()
    {
        return $this->_locale;
    }

    /**
     * Gets locale category
     */
    public function getCategory()
    {
        return $this->_category;
    }
    public function getOverride(){
        return $this->_override;
    }

    /**
     * Validator for constructor
     */
    protected function prepareOptions(array $options)
    {
        if (! isset($options["locale"])) {
            throw new Exception("Parameter \"locale\" is required");
        }

        if (! isset($options["directory"])) {
            throw new Exception("Parameter \"directory\" is required");
        }
        $this->setCacheEngine($options['cache']);
        $options = array_merge($this->getOptionsDefault(), $options);
        $this->setOverride($options['override']);
        $this->setLocale($options["category"], $options["locale"]);
        $this->setDefaultDomain($options["defaultDomain"]);
        $this->setDirectory($options["directory"]);
        $this->setDomain($options["defaultDomain"]);

    }
}
