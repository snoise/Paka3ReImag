<?php
/*
Plugin Name: Paka3Reimg
Plugin URI: http://www.paka3.com/wpplugin
Description: 画像を選択し複製後、フィルターを適用後、サムネイルを再構成してみる
Author: Shoji ENDO
Version: 0.1
Author URI:http://www.paka3.com/
*/


//オブジェクトを生成
new Paka3Reimg;
 
//クラス定義
class Paka3Reimg {
 
  public $str;
  
  //コンストラクタ
  function __construct() {
    require_once( ABSPATH . 'wp-admin/includes/image.php' );
    add_action('admin_menu', array($this, 'adminAddMenu'));

   }
  //######################
  //管理メニューの設定
  //######################
  function adminAddMenu() {
    add_submenu_page("options-general.php", 'Paka3画像加工', 'Paka3画像加工',  'edit_themes', 'paka3_re_img', array($this,'paka3_re_img'));
    add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
  }
   
  //######################
  //画像の画面と処理
  //######################
  function paka3_re_img() {
    
    //文字列の登録
    if($_POST['string'] && check_admin_referer('paka3reimage')){
        $opt=array('text' => $_POST['string'],
                   'fontSize'=>$_POST['fontSize'],
                   'position'=>$_POST['position'],
                   'fontColor' => $_POST['fontColor']
                   );
        update_option('paka3_reimg', $opt);
    }   
    
    //合成元の画像のIDとパス
    $imgIDs=array_unique($_POST['imgID']);
    foreach($imgIDs as $imgID){
      $imgPath = get_attached_file($imgID);
     
     if($imgPath && $_POST['string'] && isset($_POST['reImg']) && check_admin_referer('paka3reimage')){
        //##画像処理(上書きor複製)
        if($_POST['save']==1){
           //上書き
           $this->str="[上書きモード(ID:".$imgID.")]<br />";
        }else{
           //新しい画像を作成
           $this->str="[複製元(ID:".$imgID.")]";
           $imgID = $this->imageCopy($imgID,$imgPath);
           $imgPath = false;
           $this->str.=">>>>[複製(ID:".$imgID.")]<br />";
           $imgPath = $imgID ? get_attached_file( $imgID ) : false;
        }
         
        //##合成&再構成
        if($_POST['string'] && $imgPath){
            //##文字列を合成する関数
            $this->imageMergeText($imgPath,$_POST['string'],$_POST['fontSize'],$_POST['position'],$_POST['fontColor']);
            //##サムネイル再構成
            $metadata = wp_generate_attachment_metadata( $imgID , $imgPath );
            if (!empty( $metadata ) && ! is_wp_error( $metadata ) ) {
  	       wp_update_attachment_metadata( $imgID , $metadata );
               $this->str .= '画像の再構成が実行されました<br />';
            }else{
  	       $this->str .= "ID".$imgID."は、再構成されませんでした（エラー）。<br />";
  	       exit();
            }
        }else{
            $this->str ="エラー：処理は実行されませんでした。";
        }
        //更新メッセージ
        $img = wp_get_attachment_image($imgID);
        $url = get_admin_url('','post.php')."?post={$imgID}&action=edit";
        echo '<div class="updated fade"><p><strong>';
        echo $this->str;
        echo "<a href={$url} target=_blank>{$img}</a></strong></p></div>";
        
      }
    }
    //文字列の呼び出し
    $opt = get_option('paka3_reimg');
    $add_text = isset($opt['text']) ? $opt['text']: null;
    //フォントサイズ
    $fontSize = isset($opt['fontSize']) ? $opt['fontSize']: null;
    $sizeList  = array(
	         array(54,54,""),
	         array(48,48,""),
	         array(42,42,""),
	         array(36,36,""),
		 array(30,30,""),
		 array(24,24,""),
		 array(18,18,""),
                 array(12,12,""),
		 );
    //
    $position   = isset($opt['position']) ? $opt['position']: null;
    $positions  = array(
                   array("上:左","t_left",""),
                   array("上:中央","t_center",""),
                   array("上:右","t_right",""),
                   array("中:左","m_left",""),
                   array("中:中央","m_center",""),
                   array("中:右","m_right",""),
                   array("下:左","d_left",""),
                   array("下:中央","d_center",""),
                   array("下:右","d_right",""),
		 );
    //
    $fontColor = isset($opt['fontColor']) ? $opt['fontColor']: array("FFFFFF",30);

    $wp_n = wp_nonce_field('paka3reimage');
    echo <<< EOS
    <div class="wrap">
       <h2>画像に署名（テキスト）を合成する</h2>
      画像に署名（テキスト）を合成します。通常、画像を複製されます。<br /><br />
       <div class="paka3class">
          <form action="" method="post">
	  {$wp_n}
          
	  <input name="reImg" type="hidden" value="1"/>
          署名:<input name="string" type="text" value="{$add_text}"/>
          <br />サイズ
EOS;

    echo "<select name='fontSize'>";
    foreach($sizeList as $d){
		$d[2]= selected( $fontSize, $d[1] ,false);
		echo <<<EOS
		<option value="{$d[1]}" {$d[2]}>{$d[0]}
EOS;
	}
    echo "</select>";
    
    echo "配置：<select name='position'>";
    foreach($positions as $d){
		$d[2]= selected( $position, $d[1] ,false);
		echo <<<EOS
		<option value="{$d[1]}" {$d[2]}>{$d[0]}
EOS;
	}
    echo "</select>";
    echo <<< EOS
          色：#<input name="fontColor[0]" type="text" value="{$fontColor[0]}" class="color" size=6/>
          透明度：
          <select name="fontColor[1]">
EOS;
    for ($i = 100; $i >= 0; $i=$i-10) {
        $selected = selected( $fontColor[1], $i ,false);
        echo '<option value="'.$i.'" '.$selected.'>'.$i.'%';
    }
    
    echo <<< EOS
          </select>
          <br />
          <div id="paka3images"></div>
          <br class=paka3ImageEnd>
          <button id="paka3media" type="button" class="button">合成する画像を選択</button><br />
          
          <label for="save">
            <input id="save" name="save" type="checkbox" value="1"/>
            上書きする</label>
	  <p class="submit"><input type="submit" name="Submit" class="button-primary" value="署名を合成する" /></p>
	  </form>
       </div>
       <a href="http://jscolor.com/" target="_blank">
       ※カラーピッカーはjscolorを使っています。http://jscolor.com/</a>
    </div>
EOS;
  }
  //######################
  //画像の複製と新規画像ID
  //######################
  function imageCopy($imgID,$imgPath){
        $imgURL  = wp_get_attachment_link( $imgID );
        $d=date("U");
        $newImgPath = dirname($imgPath).'/'.$d."_". basename( $imgPath );
        if(copy($imgPath,$newImgPath)){
             //同一ディレクトリに保存
             $wp_filetype = wp_check_filetype(basename($newImgPath), null );
             $attachment = array(
               'guid'  => $imgURL . '/' . basename( $newImgPath ),
               'post_mime_type' => $wp_filetype['type'],
               'post_title' => preg_replace('/\.[^.]+$/', '', basename($newImgPath)),
               'post_content' => '',
               'post_status' => 'inherit'
              );
           $imgID = wp_insert_attachment( $attachment,  $newImgPath , 0 );
        }else{
           $this->str.="複製失敗<br />";
           $imgID = false;
        }
        return $imgID;
  }
  
  
 
  //######################
  //テキスト合成例
  //######################
  function imageMergeText($imgPath,$text="",$fontSize="12",$position="m_center",$fontColor){
       //合成元
       $im = $this->paka3_imagecreate($imgPath);
       $font = dirname(__FILE__).'/ipaexg.ttf';
      
       $alpha=127-(127*$fontColor[1]*0.01);
       $hc = str_split($fontColor[0], 2);
       $hc = array(bindec(decbin(hexdec($hc[0]))),bindec(decbin(hexdec($hc[1]))),bindec(decbin(hexdec($hc[2]))));
       $color= imagecolorallocatealpha($im, $hc[0], $hc[1], $hc[2],$alpha);
 
        //imagettfbbox：テキストのサイズを取得
        //absは絶対値
        $textBox = imagettfbbox($fontSize, 0, $font, $text);
        
        $textWidth = abs($textBox[4]-$textBox[6]);
        $textHeight = abs($textBox[3]-$textBox[5]);

        switch ($position){
           case 'm_left':
               $textX = 3;
               $textY=imagesy($im)/2;//-$textHeight;
           break;
           case 'm_right':
               $textX=imagesx($im)-$textWidth-5;
               $textY=imagesy($im)/2;//-$textHeight;
           break;
           case 't_left':
              $textX = 3;
              $textY = $textHeight+3;
           break;
           case 't_center':
              $textX=(imagesx($im)-$textWidth)/2;
              $textY = $textHeight+3;
           break;
           case 't_right':
              $textX=imagesx($im)-$textWidth-5;
              $textY = $textHeight+3;
           break;
           case 'd_left':
              $textX = 3;
              $textY=imagesy($im)-3;
           break;
           case 'd_center':
              $textX=(imagesx($im)-$textWidth)/2;
              $textY=imagesy($im)-3;//-$textHeight;
           break;
           case 'd_right':
              $textX=imagesx($im)-$textWidth-5;
              $textY=imagesy($im)-3;//-$textHeight;
           break;
           default: //m_center
             $textX=(imagesx($im)-$textWidth)/2;
             $textY=imagesy($im)/2;//-$textHeight;
        }
        //$bgc= imagecolorallocatealpha($im, 0, 0, 0, 50);
        //imagefilledrectangle($im, $textX, $textY-$textHeight, $textX+imagesx($im), $textY, $bgc);

       if($im && imagettftext($im, $fontSize, 0, $textX, $textY, $color, $font, $text)){
          $this->str .= '※署名を合成しました。<br />';
          //保存
          $this->paka3_image($im, $imgPath);
          imagedestroy($im);
       }else{
          $this->str .= '署名が失敗しました。<br />';
       }
      
  }
  
  
  
  //######################
  //画像のイメージ作成(jpeg/png/gif)
  //######################
   function paka3_imagecreate($imgPath){
       $mime = wp_check_filetype(basename($imgPath), null );
       if($mime['type'] == "image/jpeg"){
           $im = imagecreatefromjpeg($imgPath);
       }elseif($mime['type'] == "image/png"){
           $im = imagecreatefrompng($imgPath);
       }elseif($mime['type'] == "image/gif"){
           $im = imagecreatefromgif($imgPath);
       } else{
           $im = false;
       }
       return $im;
   }
  
  //######################
  //画像の保存
  //######################
  function paka3_image($im,$imgPath){
        $mime = wp_check_filetype(basename($imgPath), null );
        if($mime['type'] == "image/jpeg"){
            imagejpeg($im, $imgPath);
        }elseif($mime['type'] == "image/png"){
            imagepng($im, $imgPath);
        }elseif($mime['type'] == "image/gif"){
            imagegif($im, $imgPath);
        }else{
            return false;
        }
  }

  
  //######################
  //読み込むCSS&
  //######################
  function admin_scripts($hook_suffix){
   if($hook_suffix=="settings_page_paka3_re_img" ){ 
    wp_enqueue_media(); // メディアアップローダー用のスクリプトをロードする
    // カスタムメディアアップローダー用のJavaScript
    wp_enqueue_script(
        'my-media-uploader',
	
	//**javasctiptの指定
        plugins_url("paka3-uploader.js", __FILE__),
        
	array('jquery'),
        filemtime(dirname(__FILE__).'/paka3-uploader.js'),
        false
    );
    //カラーピッカー
    wp_enqueue_script(
        'jscolor_script',
         plugin_dir_url( __FILE__ ) . 'jscolor/jscolor.js' );
    //スタイルシート
    echo <<< EOS
      <style type="text/css">
	#paka3images div{
	    float:left;
	    margin: 10px;
	    height: 100px;
	    overflow:hidden;
	}
        #paka3images img 
        {
            max-width: 100px;
           
            border: 1px solid #cccccc;
        }
	.paka3ImageEnd{
		clear:left
	}
        </style>
EOS;
   }
  }
  
  
}


?>