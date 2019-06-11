<?php

if(!function_exists('asset')){
    function asset($path){
        $config = C('ASSET');
        return $config['prefix'] . $path;
    }
}

if(!function_exists('old')) {
    function old($key, $default = null)
    {
        return \Common\Lib\Flash::get('qs_old_input.' . $key, $default);
    }
}

if(!function_exists('flashError')) {
    function flashError($err_msg)
    {
        \Common\Lib\FlashError::set($err_msg);
    }
}

if(!function_exists('verifyAuthNode')) {
    function verifyAuthNode($node)
    {
        list($module_name, $controller_name, $action_name) = explode('.', $node);
        return \Common\Util\GyRbac::AccessDecision($module_name, $controller_name, $action_name) ? 1 : 0;
    }
}

/**
 * 配合文件上传插件使用  把file_ids转化为srcjson
 * example: $ids = '1,2'
 *   return: [ "https:\/\/csh-pub-resp.oss-cn-shenzhen.aliyuncs.com\/Uploads\/image\/20181123\/5bf79e7860393.jpg",
 *          //有数据的时候返回showFileUrl($id)的结果
 *    ''    //没有数据时返回空字符串
 *   ];
 * @param $ids array|string file_ids
 * @return string data srcjson
 */
if(!function_exists('fidToSrcjson')) {
    function fidToSrcjson($ids)
    {
        if ($ids) {
            if (!is_array($ids)) {
                $ids = explode(',', $ids);
            }
            $json = [];
            foreach ($ids as $id) {
                $json[] = showFileUrl($id);
            }
            return htmlentities(json_encode($json));
        } else {
            return '';
        }
    }
}


/**
 * 裁剪字符串
 *   保证每个裁切的字符串视觉长度一致,而curLength裁剪会导致视觉长度参差不齐
 *   frontCutLength: 中文算2个字符长度，其他算1个长度
 *   curLength:      每个字符都是算一个长度
 *
 *   example1: 若字符串长度小等于$len,将会原样输出$str;
 *   frontCutLength('字符1',5)；    @return: '字符1';
 *
 *   example2: 若字符串长度大于$len
 *   frontCutLength('字符12',5)；   @return: '字...';(最后的"..."会算入$len)
 *
 *   example3: 若字符串长度大于$len，且最大长度的字符不能完整输出,则最大长度的字符会被忽略
 *   frontCutLength('1字符串',5)；  @return: '1....';("字"被省略，最后的"..."会算入$len)
 *
 * @param $str string 要截的字符串
 * @param $len int|string 裁剪的长度 按英文的长度计算
 * @return false|string
 */
if(!function_exists('frontCutLength')) {
    function frontCutLength($str, $len)
    {
        $gbStr = iconv('UTF-8', 'GBK', $str);
        $count = strlen($gbStr);
        if ($count <= $len) {
            return $str;
        }
        $gbStr = mb_strcut($gbStr, 0, $len - 3, 'GBK');

        $str = iconv('GBK', 'UTF-8', $gbStr);
        return $str . '...';
    }
}


//展示数据库存储文件URL地址
if(!function_exists('showFileUrl')){
    function showFileUrl($file_id){
        if(filter_var($file_id, FILTER_VALIDATE_URL)){
            return $file_id;
        }

        $file_pic = M('FilePic');
        $file_pic_ent = $file_pic->where(array('id' => $file_id))->find();

        if(!$file_pic_ent){
            return '';
        }

        //如果图片是网络链接，直接返回网络链接
        if(!empty($file_pic_ent['url']) && $file_pic_ent['security'] != 1){
            return $file_pic_ent['url'];
        }

        if($file_pic_ent['security'] == 1){
            //alioss
            if(!empty($file_pic_ent['url'])){
                $ali_oss = new \Common\Util\AliOss();
                $config = C('UPLOAD_TYPE_' . strtoupper($file_pic_ent['cate']));
                $object = trim(str_replace($config['oss_host'], '', $file_pic_ent['url']), '/');
                $url = $ali_oss->getOssClient($file_pic_ent['cate'])->signUrl($object, 60);
                return $url;
            }

            if(strtolower(MODULE_NAME) == 'admin' || $file_pic_ent['owner'] == session(C('USER_AUTH_KEY'))){

                session('file_auth_key', $file_pic_ent['owner']);
                return U('/api/upload/load', array('file_id' => $file_id));
            }
        }
        else{
            return UPLOAD_PATH . '/' . $file_pic_ent['file'];
        }
    }
}


if(!function_exists('getAutocropConfig')) {
    function getAutocropConfig($key)
    {
        $ent = D('Addons')->where(['name' => 'AutoCrop', 'status' => 1])->find();
        $config = json_decode($ent['config'], true);
        $config = json_decode(html_entity_decode($config['config']), true);
        return $config[$key];
    }
}


//取缩略图
if(!function_exists('showThumbUrl')) {
    function showThumbUrl($file_id, $prefix, $replace_img = '')
    {
        if (filter_var($file_id, FILTER_VALIDATE_URL)) {
            return $file_id;
        }

        $file_pic = M('FilePic');
        $file_pic_ent = $file_pic->where(array('id' => $file_id))->find();
        //自动填充的测试数据处理
        if ($file_pic_ent['seed'] && $file_pic_ent['url'] && ($config = getAutocropConfig($prefix))) {
            $width = $config[0];
            $high = $config[1];

            return preg_replace('/(http[s]?\:\/\/[a-z0-9\-\.\_]+?)\/(\d+?)\/(\d+)(.*)/i', "$1/{$width}/{$high}$4", $file_pic_ent['url']);
        }

        if (!$file_pic_ent && !$replace_img) {
            //不存在图片时，显示默认封面图
            $file_pic_ent = $file_pic->where(array('id' => C('DEFAULT_THUMB')))->find();
        }
        $file_name = basename(UPLOAD_DIR . '/' . $file_pic_ent['file']);
        $thumb_path = UPLOAD_DIR . '/' . str_replace($file_name, $prefix . '_' . $file_name, $file_pic_ent['file']);
        //当file字段不存在值时，程序编程检测文件夹是否存在，依然会通过。因此要加上当file字段有值这项条件
        if (file_exists($thumb_path) === true && !empty($file_pic_ent['file'])) {

            return UPLOAD_PATH . '/' . str_replace($file_name, $prefix . '_' . $file_name, $file_pic_ent['file']);
        } elseif ($replace_img) {
            return $replace_img;
        } else {
            return showFileUrl($file_id);
        }
    }
}