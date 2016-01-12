<?php

/**
 * 整理：宿迁老葛
 * QQ：9411526
 * 微信公众平台后台演示：http://demo.abis.com.cn/setup
 * 用户名：admin
 * 密码：123456
 * 
 * 调用方式,得到指定状态商品列表
 * 
 *       $IncludePath = C('IncludePath');
 *       require_once $IncludePath . 'Weixin/wechat-php-sdk-master/' . 'merchant.class.php';
 *       
 *       $options = array(
 *           'token' => C('weixin_base_token'),
 *           'appid' => C('weixin_api_appId'),
 *           'appsecret' => C('weixin_api_appSecret'),
 *           'encodingaeskey' => C('weixin_api_encodingaeskey')
 *       );
 *       $weMerchant = new \Merchant($options);
 *       
 *       $data = array();
 *       $data['status'] = '0';
 *       $result = $weMerchant->products_getbystatus($data);
 *       
 *       dump($result);
 *       return;
 * 
 * 
 * 
 * 
 * @author Administrator
 *
 */
class Merchant
{

    const API_URL_PREFIX = 'https://api.weixin.qq.com/cgi-bin';

    const AUTH_URL = '/token?grant_type=client_credential&';

    const API_URL_MERCHANT = 'https://api.weixin.qq.com/merchant';

    private $token;

    private $encodingAesKey;

    private $encrypt_type;

    private $appid;

    private $appsecret;

    private $access_token;

    public $errCode = 40001;

    public $errMsg = "no access";

    public function __construct($options)
    {
        $this->token = isset($options['token']) ? $options['token'] : '';
        $this->appid = isset($options['appid']) ? $options['appid'] : '';
        $this->appsecret = isset($options['appsecret']) ? $options['appsecret'] : '';
        $this->encodingAesKey = isset($options['encodingaeskey']) ? $options['encodingaeskey'] : '';
    }

    /**
     * GET 请求
     *
     * @param string $url            
     */
    public function http_get($url)
    {
        $oCurl = curl_init();
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, FALSE);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); // CURL_SSLVERSION_TLSv1
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    }

    /**
     * POST 请求
     *
     * @param string $url            
     * @param array $param            
     * @param boolean $post_file
     *            是否文件上传
     * @return string content
     */
    public function http_post($url, $param, $post_file = false)
    {
        $oCurl = curl_init();
        if (stripos($url, "https://") !== FALSE) {
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($oCurl, CURLOPT_SSLVERSION, 1); // CURL_SSLVERSION_TLSv1
        }
        if (is_string($param) || $post_file) {
            $strPOST = $param;
        } else {
            $aPOST = array();
            foreach ($param as $key => $val) {
                $aPOST[] = $key . "=" . urlencode($val);
            }
            $strPOST = join("&", $aPOST);
        }
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_POST, true);
        curl_setopt($oCurl, CURLOPT_POSTFIELDS, $strPOST);
        $sContent = curl_exec($oCurl);
        $aStatus = curl_getinfo($oCurl);
        curl_close($oCurl);
        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    }

    /**
     * 微信api不支持中文转义的json结构
     *
     * @param array $arr            
     */
    static function json_encode($arr)
    {
        $parts = array();
        $is_list = false;
        // Find out if the given array is a numerical array
        $keys = array_keys($arr);
        $max_length = count($arr) - 1;
        if (($keys[0] === 0) && ($keys[$max_length] === $max_length)) { // See if the first key is 0 and last key is length - 1
            $is_list = true;
            for ($i = 0; $i < count($keys); $i ++) { // See if each key correspondes to its position
                if ($i != $keys[$i]) { // A key fails at position check.
                    $is_list = false; // It is an associative array.
                    break;
                }
            }
        }
        foreach ($arr as $key => $value) {
            if (is_array($value)) { // Custom handling for arrays
                if ($is_list)
                    $parts[] = self::json_encode($value); /* :RECURSION: */
                else
                    $parts[] = '"' . $key . '":' . self::json_encode($value); /* :RECURSION: */
            } else {
                $str = '';
                if (! $is_list)
                    $str = '"' . $key . '":';
                    // Custom handling for multiple data types
                if (! is_string($value) && is_numeric($value) && $value < 2000000000)
                    $str .= $value; // Numbers
                elseif ($value === false)
                    $str .= 'false'; // The booleans
                elseif ($value === true)
                    $str .= 'true';
                else
                    $str .= '"' . addslashes($value) . '"'; // All other things
                $parts[] = $str;
            }
        }
        $json = implode(',', $parts);
        if ($is_list)
            return '[' . $json . ']'; // Return numerical JSON
        return '{' . $json . '}'; // Return associative JSON
    }

    /**
     * 获取缓存，按需重载
     *
     * @param string $cachename            
     * @return mixed
     */
    public function getCache($cachename = '')
    {
        if ($cachename == 'access_token') {
            $access_token = C('weixin_access_token');
            $weixin_access_token_expires_in_endtime = C('weixin_access_token_expires_in_endtime');
            
            if (intval($weixin_access_token_expires_in_endtime) < time()) {
                
                $appid = $this->appid;
                $appsecret = $this->appsecret;
                
                $url = self::API_URL_PREFIX . self::AUTH_URL . 'appid=' . $appid . '&secret=' . $appsecret;
                $result = $this->http_get(self::API_URL_PREFIX . self::AUTH_URL . 'appid=' . $appid . '&secret=' . $appsecret);
                
                if ($result) {
                    $json = json_decode($result, true);
                    if (! $json || isset($json['errcode'])) {
                        $this->errCode = $json['errcode'];
                        $this->errMsg = $json['errmsg'];
                        return false;
                    }
                    $this->access_token = $json['access_token'];
                    $expire = $json['expires_in'] ? intval($json['expires_in']) - 100 : 3600;
                    // 写入缓存
                    $settingArray = array(
                        'weixin_access_token' => $json['access_token'],
                        'weixin_access_token_expires_in_endtime' => time() + intval($json['expires_in'])
                    );
                    // 这里将新的参数值，通过后台的表单提交过来；
                    $settingstr = "<?php \n return array(\n";
                    foreach ($settingArray as $key => $v) {
                        $settingstr .= "\n\t'" . $key . "'=>'" . $v . "',";
                    }
                    $settingstr .= "\n);\n\n";
                    $setfile = C('CONFIG_WEIXIN_ACCESSTOKEN_FILE');
                    file_put_contents($setfile, $settingstr);
                    
                    return $this->access_token;
                }
                return false;
            } else {
                $this->access_token = $access_token;
                return $this->access_token;
            }
            return $this->access_token;
        } elseif ($cachename == 'jsapi_ticket') {
            
            $jsapi_ticket = C('weixin_jsapi_ticket');
            $weixin_jsapi_ticket_expires_in_endtime = C('weixin_jsapi_ticket_expires_in_endtime');
            
            if (intval($weixin_jsapi_ticket_expires_in_endtime) < time()) {
                
                $appid = $this->appid;
                $appsecret = $this->appsecret;
                
                $result = $this->http_get(self::API_URL_PREFIX . self::GET_TICKET_URL . 'access_token=' . $this->access_token . '&type=jsapi');
                if ($result) {
                    $json = json_decode($result, true);
                    if (! $json || ! empty($json['errcode'])) {
                        $this->errCode = $json['errcode'];
                        $this->errMsg = $json['errmsg'];
                        return false;
                    }
                    $this->jsapi_ticket = $json['ticket'];
                    $expire = $json['expires_in'] ? intval($json['expires_in']) - 100 : 3600;
                    // 写入缓存
                    $settingArray = array(
                        'weixin_jsapi_ticket' => $json['ticket'],
                        'weixin_jsapi_ticket_expires_in_endtime' => time() + intval($json['expires_in'])
                    );
                    // 这里将新的参数值，通过后台的表单提交过来；
                    $settingstr = "<?php \n return array(\n";
                    foreach ($settingArray as $key => $v) {
                        $settingstr .= "\n\t'" . $key . "'=>'" . $v . "',";
                    }
                    $settingstr .= "\n);\n\n";
                    $setfile = C('CONFIG_WEIXIN_JSAPITICKET_FILE');
                    file_put_contents($setfile, $settingstr);
                    return $this->jsapi_ticket;
                }
                return false;
            } else {
                $this->jsapi_ticket = $jsapi_ticket;
                return $this->jsapi_ticket;
            }
            return $this->jsapi_ticket;
        }
    }

    /**
     * 清除缓存，按需重载
     *
     * @param string $cachename            
     * @return boolean
     */
    protected function removeCache($cachename)
    {
        return false;
    }

    /**
     * 获取access_token
     *
     * @param string $appid
     *            如在类初始化时已提供，则可为空
     * @param string $appsecret
     *            如在类初始化时已提供，则可为空
     * @param string $token
     *            手动指定access_token，非必要情况不建议用
     */
    public function checkAuth($appid = '', $appsecret = '', $token = '')
    {
        if (! $appid || ! $appsecret) {
            $appid = $this->appid;
            $appsecret = $this->appsecret;
        }
        if ($token) { // 手动指定token，优先使用
            $this->access_token = $token;
            return $this->access_token;
        }
        
        $authname = 'access_token';
        $rs = $this->getCache($authname);
        $this->access_token = $rs;
        return $rs;
    }

    /**
     * 1.1 增加商品
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function product_create($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/create?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 1.2 删除商品
     * 调用方式
     * $data=array();
     * $data['xxxxxxx'] = '0';//product_id 商品ID
     * 字段内容
     * product_id 商品ID
     * $result = product_del($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function product_del($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/get?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 1.3 修改商品
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function product_update($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/get?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 1.4 查询商品
     * 调用方式
     * $data=array();
     * $data['product_id'] = '0';//product_id 商品ID
     * $result = product_get($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function product_get($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/get?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 1.5 获取指定状态的所有商品
     * 调用方式
     * $data=array();
     * $data['status'] = '0';//status 商品状态(0-全部, 1-上架, 2-下架)
     * $result = products_getbystatus($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function products_getbystatus($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/getbystatus?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 1.6 商品上下架
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * product_id 商品ID
     * status 商品上下架标识(0-下架, 1-上架)
     *
     * $result = product_modproductstatus($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function product_modproductstatus($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/modproductstatus?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 1.7 获取指定分类的所有子分类
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * cate_id 大分类ID(根节点分类id为1)
     *
     * $result = category_getsub($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function category_getsub($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/category/getsub?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 1.8 获取指定子分类的所有SKU
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * cate_id 商品子分类ID
     *
     * $result = category_getsku($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function category_getsku($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/category/getsku?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 1.9 获取指定分类的所有属性
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * cate_id 商品子分类ID
     *
     * $result = category_getproperty($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function category_getproperty($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/category/getproperty?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 2.1 增加库存
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * product_id 商品ID
     * sku_info sku信息,格式"id1:vid1;id2:vid2",如商品为统一规格，则此处赋值为空字符串即可
     * quantity 增加的库存数量 *
     *
     * $result = stock_add($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function stock_add($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/stock/add?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 2.2 减少库存
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * product_id 商品ID
     * sku_info sku信息, 格式"id1:vid1;id2:vid2"
     * quantity 减少的库存数量
     *
     * $result = stock_reduce($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function stock_reduce($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/stock/reduce?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 3.1 增加邮费模板
     * 调用方式
     * $data=array();
     *
     * $result = express_add($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function express_add($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/express/add?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 3.2 删除邮费模板
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * template_id 邮费模板ID
     *
     * $result = express_del($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function express_del($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/express/del?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 3.3 修改邮费模板
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * template_id 邮费模板ID
     * delivery_template 邮费模板信息(字段说明详见增加邮费模板) *
     *
     * $result = express_update($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function express_update($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/express/update?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 3.4 获取指定ID的邮费模板
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * template_id 邮费模板ID
     *
     * $result = express_getbyid($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function express_getbyid($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/express/getbyid?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 3.5 获取所有邮费模板
     * 调用方式
     *
     * $result = express_getall();
     *
     * @return boolean|mixed
     */
    public function express_getall()
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/express/getall?access_token=" . $this->access_token;
        
        $result = $this->http_get($url);
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 4.1 增加分组
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * group_name 分组名称
     * product_list 商品ID集合
     *
     * $result = group_add($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function group_add($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/group/add?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 4.2 删除分组
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * group_id 分组ID
     *
     * $result = group_del($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function group_del($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/group/del?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 4.3 修改分组属性
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * "group_id": 28,
     * "group_name":"特惠专场"
     *
     *
     * $result = group_propertymod($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function group_propertymod($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/group/propertymod?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 4.4 修改分组商品
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     *
     * $result = group_productmod($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function group_productmod($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/group/productmod?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 4.5 获取所有分组
     * 调用方式
     *
     * $result = group_getall();
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function group_getall()
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/group/getall?access_token=" . $this->access_token;
        
        $result = $this->http_get($url);
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 4.6 根据分组ID获取分组信息
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * 相关字段
     * group_id 分组ID
     *
     * $result = group_getbyid($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function group_getbyid($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/group/getbyid?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 5.1 增加货架
     * 调用方式
     * $data=array();
     *
     * $result = shelf_add($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function shelf_add($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/shelf/add?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 5.2 删除货架
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * shelf_id 货架ID
     *
     * $result = shelf_add($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function shelf_del($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/shelf/del?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 5.3 修改货架
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * shelf_id 货架ID
     * shelf_data 货架详情(字段说明详见增加货架)
     * shelf_banner 货架banner(图片需调用图片上传接口获得图片Url填写至此，否则修改货架失败)
     * shelf_name 货架名称
     *
     *
     * $result = shelf_mod($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function shelf_mod($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/shelf/mod?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 5.4 获取所有货架
     * 调用方式
     *
     * $result = shelf_getall();
     *
     * @return boolean|mixed
     */
    public function shelf_getall()
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/shelf/getall?access_token=" . $this->access_token;
        
        $result = $this->http_get($url);
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 5.5 根据货架ID获取货架信息
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * shelf_id 货架ID
     *
     * $result = shelf_getbyid($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function shelf_getbyid($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/shelf/getbyid?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 6.2 根据订单ID获取订单详情
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * order_id 订单ID
     *
     * $result = order_getbyid($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function order_getbyid($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/order/getbyid?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 6.3 根据订单状态/创建时间获取订单详情
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * status 订单状态(不带该字段-全部状态, 2-待发货, 3-已发货, 5-已完成, 8-维权中, )
     * begintime 订单创建时间起始时间(不带该字段则不按照时间做筛选)
     * endtime 订单创建时间终止时间(不带该字段则不按照时间做筛选)
     *
     *
     * $result = order_getbyfilter($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function order_getbyfilter($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/order/getbyfilter?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 6.4 设置订单发货信息
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * order_id 订单ID
     * delivery_company 物流公司ID(参考《物流公司ID》；
     * 当need_delivery为0时，可不填本字段；
     * 当need_delivery为1时，该字段不能为空；
     * 当need_delivery为1且is_others为1时，本字段填写其它物流公司名称)
     * delivery_track_no 运单ID(
     * 当need_delivery为0时，可不填本字段；
     * 当need_delivery为1时，该字段不能为空；
     * )
     * need_delivery 商品是否需要物流(0-不需要，1-需要，无该字段默认为需要物流)
     * is_others 是否为6.4.5表之外的其它物流公司(0-否，1-是，无该字段默认为不是其它物流公司)
     *
     *
     *
     * $result = order_getbyfilter($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function order_setdelivery($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/order/setdelivery?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

    /**
     * 6.5 关闭订单
     * 调用方式
     * $data=array();
     * $data['xxxxxx'] = '0';
     * order_id 订单ID
     *
     *
     * $result = order_getbyfilter($data);
     *
     * @param unknown $data            
     * @return boolean|mixed
     */
    public function order_close($data)
    {
        if (! $this->access_token && ! $this->checkAuth()) {
            return false;
        }
        
        $url = self::API_URL_MERCHANT . "/order/close?access_token=" . $this->access_token;
        
        $result = $this->http_post($url, self::json_encode($data));
        if ($result) {
            $resultArray = json_decode($result, true);
            if (! $resultArray || ! empty($resultArray['errcode'])) {
                $this->errCode = $resultArray['errcode'];
                $this->errMsg = $resultArray['errmsg'];
                return $resultArray;
            }
            return $resultArray;
        }
        return false;
    }

/**
 * 7.1 上传图片---暂未完工
 * 调用方式
 * $data=array();
 * $data['filename'] = 'test.png';
 * *是 图片数据
 * $result = common_upload_img($data);
 *
 * @param unknown $data            
 * @return boolean|mixed
 */
    // public function common_upload_img($data)
    // {
    // if (! $this->access_token && ! $this->checkAuth()) {
    // return false;
    // }
    
    // $url = self::API_URL_MERCHANT . "/common/upload_img?access_token=" . $this->access_token . "&filename=" . $data['filename'];
    
    // $result = $this->http_post($url, self::json_encode($data));
    // if ($result) {
    // $resultArray = json_decode($result, true);
    // if (! $resultArray || ! empty($resultArray['errcode'])) {
    // $this->errCode = $resultArray['errcode'];
    // $this->errMsg = $resultArray['errmsg'];
    // return $resultArray;
    // }
    // return $resultArray;
    // }
    // return false;
    // }
}
