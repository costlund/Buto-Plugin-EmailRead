<?php
class PluginEmailRead{
  public $server = null;
  public $port = null;
  public $user = null;
  public $password = null;
  public $folder = null;  
  public function get_messages(){
    /**
     * 
     */
    $data = new PluginWfArray();
    $data->set('server', $this->server);
    $data->set('port', $this->port);
    $data->set('user', $this->user);
    $data->set('password', $this->password);
    $data->set('folder', $this->folder);
    $data->set('files_folder', __DIR__.'/temp');
    $data->set('mailbox', '{'.$data->get('server').':'.$data->get('port').'}INBOX');
    if($data->get('folder')){
      $data->set('mailbox', $data->get('mailbox').'.'.$data->get('folder'));
    }
    $inbox = imap_open($data->get('mailbox'), $data->get('user'), $data->get('password')) or die('Cannot connect to mail server due to: ' . imap_last_error());  
    $data->set('list', imap_list($inbox, '{'.$data->get('server').'}', '*'));
    $data->set('emails_count', 0);
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
         * structure
         */
        $structure = imap_fetchstructure($inbox, $msgno);
        wfPlugin::includeonce('object/to_array');
        $ObjectTo_array = new PluginObjectTo_array();
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
            if($part->get('parts')){
              foreach($part->get('parts') as $k2 => $v2){
                $part2 = new PluginWfArray($v2);
                if($part2->get('subtype')=='PNG'){
                  $filename = 'temp.png';
                  if($part2->get('dparameters/0/attribute')=='filename'){
                    $filename = $part2->get('dparameters/0/value');
                  }
                  $fetchbody = imap_fetchbody($inbox, $msgno, ((string)$k+1).'.'.((string)$k2+1) );
                  $fetchbody = base64_decode($fetchbody);
                  $email->set('files/', array('filename' => $filename, 'fetchbody' => $fetchbody));
                  if(false){
                    wfFilesystem::createDir($data->get('files_folder').'/'.$email->get('overview/uid'));
                    wfFilesystem::saveFile($data->get('files_folder').'/'.$email->get('overview/uid').'/'.$filename, $fetchbody);
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
}
