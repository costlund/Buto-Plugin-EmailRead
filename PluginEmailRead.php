<?php
class PluginEmailRead{
  public $server = null;
  public $port = null;
  public $user = null;
  public $password = null;
  public $folder = null;  
  public $files_folder = null;  
  private $subtype = array();
  private $filename = null;
  function __construct(){
    $this->subtype = array('PNG', 'JPEG', 'PDF', 'MP4', 'PLAIN');
    $this->filename = new PluginWfArray();
    $this->filename->set('value', null);
    $this->filename->set('PNG/name', 'temp.png');
    $this->filename->set('PNG/encoding', 3);
    $this->filename->set('JPEG/name', 'temp.jpg');
    $this->filename->set('JPEG/encoding', 3);
    $this->filename->set('PDF/name', 'temp.pdf');
    $this->filename->set('PDF/encoding', 3);
    $this->filename->set('MP4/name', 'temp.mp4');
    $this->filename->set('MP4/encoding', 3);
    $this->filename->set('PLAIN/name', 'temp.txt');
    $this->filename->set('PLAIN/encoding', 4);
  }
  public function get_messages(){
    /**
     * 
     */
    wfPlugin::includeonce('object/to_array');
    $ObjectTo_array = new PluginObjectTo_array();
    /**
     * 
     */
    $data = new PluginWfArray();
    $data->set('server', $this->server);
    $data->set('port', $this->port);
    $data->set('user', $this->user);
    $data->set('password', $this->password);
    $data->set('folder', $this->folder);
    $data->set('files_folder', $this->files_folder);
    $data->set('mailbox', '{'.$data->get('server').':'.$data->get('port').'}INBOX');
    if($data->get('folder')){
      $data->set('mailbox', $data->get('mailbox').'.'.$data->get('folder'));
    }
    $inbox = imap_open($data->get('mailbox'), $data->get('user'), $data->get('password')) or die('Cannot connect to mail server due to: ' . imap_last_error());  
    $data->set('list', imap_list($inbox, '{'.$data->get('server').'}', '*'));
    $data->set('emails_count', 0);
    $data->set('emails', array());
    /**
     * emails
     */
    $emails   = imap_search($inbox, 'ALL');
    if($emails){
      foreach ($emails as $key => $msgno) {
        /**
         * 
         */
        $email = new PluginWfArray();
        /**
         * overview
         */
        $overview = imap_fetch_overview($inbox,$msgno,0);
        $email->set('overview', (array) $overview[0]);
        $email->set('overview/subject', mb_decode_mimeheader($email->get('overview/subject')));
        $email->set('overview/from', mb_decode_mimeheader($email->get('overview/from')));
        /**
         * header
         */
        $header = imap_headerinfo($inbox, $msgno);
        $email->set('header', $ObjectTo_array->to_array($header));
        $email->set('header/from_email', $email->get('header/from/0/mailbox').'@'.$email->get('header/from/0/host'));
        $email->set('header/reply_to_email', $email->get('header/reply_to/0/mailbox').'@'.$email->get('header/reply_to/0/host'));
        /**
         * structure
         */
        $structure = imap_fetchstructure($inbox, $msgno);
        $email->set('structure', $ObjectTo_array->to_array($structure));
        /**
         * message
         */
        $message = imap_fetchbody($inbox,$msgno, 1);
        if($email->get('structure/encoding')==3){
          $message = imap_base64($message);
        }elseif($email->get('structure/encoding')==1){
          $message = imap_8bit($message);
        }else{
          $message = quoted_printable_decode($message);
        }
        $email->set('message', $message);
        /**
         * files
         */
        $email->set('files', array());
        if($email->get('structure/parts')){
          foreach($email->get('structure/parts') as $k => $v){
            $part = new PluginWfArray($v);
            if(in_array($part->get('subtype'), $this->subtype)){
              $file = $this->get_file($inbox, $msgno, $part, ((string)$k+1), $email->get('overview/uid'), $data);
              if($file){
                $email->set('files/', $file);
              }
            }
            if($part->get('parts')){
              foreach($part->get('parts') as $k2 => $v2){
                $part2 = new PluginWfArray($v2);
                if(in_array($part2->get('subtype'), $this->subtype)){
                  $file = $this->get_file($inbox, $msgno, $part2, ((string)$k+1).'.'.((string)$k2+1), $email->get('overview/uid'), $data);
                  if($file){
                    $email->set('files/', $file);
                  }
                }
              }
            }
          }
        }
        /**
         * 
         */
        $data->set('emails/', $email->get());
      }
    }
    /**
     * close
     */
    imap_close($inbox);
    /**
     * emails_count
     */
    $data->set('emails_count', sizeof($data->get('emails')));
    /**
     * 
     */
    return $data;
  }
  private function get_file($inbox, $msgno, $part, $section, $uid, $data){
    /**
     * filename
     */
    $this->filename->set('value', $this->filename->get($part->get('subtype').'/name'));
    if($part->get('dparameters/0/attribute')=='filename'){
      $this->filename->set('value', $part->get('dparameters/0/value'));
    }elseif($part->get('dparameters/1/attribute')=='filename'){
      $this->filename->set('value', $part->get('dparameters/1/value'));
    }
    /**
     * fetchbody
     */
    $fetchbody = imap_fetchbody($inbox, $msgno, $section );
    /**
     * is_file
     */
    $is_file = false;
    if($part->get('encoding')==3 || $part->get('encoding')==4){
      $is_file = true;
    }
    /**
     *
     */
    if($is_file){
      /**
       * decode
       */
      if($this->filename->get($part->get('subtype').'/encoding')==3){
        $fetchbody = base64_decode($fetchbody);
      }elseif($this->filename->get($part->get('subtype').'/encoding')==4){
        $fetchbody = quoted_printable_decode($fetchbody);
      }
      /**
       * save
       */
      if($data->get('files_folder')){
        wfFilesystem::createDir($data->get('files_folder').'/'.$uid);
        wfFilesystem::saveFile($data->get('files_folder').'/'.$uid.'/'.$this->filename->get('value'), $fetchbody);
      }
    }
    /**
     * 
     */
    if($is_file){
      return array('filename' => $this->filename->get('value'), 'fetchbody' => 'fetchbody...');
    }else{
      return false;
    }
  }
}
