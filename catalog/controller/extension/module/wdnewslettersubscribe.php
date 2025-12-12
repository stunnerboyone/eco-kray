<?php  
class ControllerExtensionModuleWDNewslettersubscribe extends Controller {
  	private $error = array();
	
	public function index($setting) {

		$this->load->model('design/banner');
		$this->load->model('tool/image');
	
		$this->language->load('extension/module/wdnewslettersubscribe');
		$this->document->addScript('catalog/view/javascript/webdigify/jquery.bpopup.min.js');
		$this->document->addScript('catalog/view/javascript/webdigify/jquery.cookie.js');

		$data['banners'] = array();

		$results = $this->model_design_banner->getBanner($setting['banner_id']);

		foreach ($results as $result) {
			if (is_file(DIR_IMAGE . $result['image'])) {
				$data['banners'][] = array(
					'image' => $this->model_tool_image->resize($result['image'], $setting['width'], $setting['height'])
				);
			}
		}
		
      $data['option_unsubscribe'] = $this->config->get('option_unsubscribe');	
		$data['thickbox'] = $setting['newslettersubscribe_thickbox'];	
		
		$this->id = 'wdnewslettersubscribe';
	    if(isset($setting['popup']) && $setting['popup']==1) {
			return $this->load->view('extension/module/wdnewsletterpopup', $data);
		}else {
			return $this->load->view('extension/module/wdnewslettersubscribe', $data);
		}
		
	 
	   	$this->load->model('account/wdnewslettersubscribe');
	   	//check db
	   	$this->model_account_wdnewslettersubscribe->check_db();
	}
	
	public function subscribe() {
	
		$prefix_eval = "";
	  
		$this->language->load('extension/module/wdnewslettersubscribe');
	 
		$this->load->model('account/wdnewslettersubscribe');
	  
		if (isset($this->request->post['subscribe_email']) and filter_var($this->request->post['subscribe_email'],FILTER_VALIDATE_EMAIL)) {
			   
			   if ($this->config->get('wdnewslettersubscribe_registered') and $this->model_account_wdnewslettersubscribe->checkRegisteredUser($this->request->post)) {
				   
					$this->model_account_wdnewslettersubscribe->UpdateRegisterUsers($this->request->post,1);
					
					echo('$("'.$prefix_eval.' #notification-normal").html("<div class=\"success\"> '.$this->language->get('subscribe').'</div>");$("'.$prefix_eval.' #subscribe-normal")[0].reset();');
				   
				
			   } else if (!$this->model_account_wdnewslettersubscribe->checkmailid($this->request->post)) {
				 
					$this->model_account_wdnewslettersubscribe->subscribe($this->request->post);
					
					echo('$("'.$prefix_eval.' #notification-normal").html("<div class=\"success\"> '.$this->language->get('subscribe').'</div>");$("'.$prefix_eval.' #subscribe-normal")[0].reset();');
				 
					if ($this->config->get('wdnewslettersubscribe_mail_status')) {
				   
						$subject = $this->language->get('mail_subject');	
						
						$message = '<table width="60%" cellpadding="2"  cellspacing="1" border="0"> 
									 <tr>
									   <td> Email Id </td>
									   <td> '.$this->request->post['subscribe_email'].' </td>
									 </tr>
									 <tr>
									   <td> Name  </td>
									   <td> '.$this->request->post['subscribe_name'].' </td>
									 </tr>';
						$message .= '</table>';
			 
						$mail = new Mail();
						$mail->protocol = $this->config->get('config_mail_protocol');
						$mail->parameter = $this->config->get('config_mail_parameter');
						$mail->hostname = $this->config->get('config_smtp_host');
						$mail->username = $this->config->get('config_smtp_username');
						$mail->password = $this->config->get('config_smtp_password');
						$mail->port = $this->config->get('config_smtp_port');
						$mail->timeout = $this->config->get('config_smtp_timeout');				
						$mail->setTo($this->config->get('config_email'));
						$mail->setFrom($this->config->get('config_email'));
						$mail->setSender($this->config->get('config_name'));
						$mail->setSubject($subject);
						$mail->setHtml($message);
						$mail->send();
					}
				 
				} else {
					  
					  echo('$("'.$prefix_eval.' #notification-normal").html("<div class=\"warning\"> '.$this->language->get('alreadyexist').'</div>");$("'.$prefix_eval.' #subscribe-normal")[0].reset();');
					  
				}
			   
			} else {
				
				echo('$("'.$prefix_eval.' #notification-normal").html("<div class=\"warning\"> '.$this->language->get('error_invalid').'</div>")');
				
			}
	  
	}
	
	public function subscribepopup() {
	
		$prefix_eval = "";
	  
		$this->language->load('extension/module/wdnewslettersubscribe');
	 
		$this->load->model('account/wdnewslettersubscribe');
	  
		if (isset($this->request->post['subscribe_pemail']) and filter_var($this->request->post['subscribe_pemail'],FILTER_VALIDATE_EMAIL)) {
				$this->request->post['subscribe_email'] = $this->request->post['subscribe_pemail']; 
				$this->request->post['subscribe_name'] = $this->request->post['subscribe_pname']; 
				
			   
			   if ($this->config->get('wdnewslettersubscribe_registered') and $this->model_account_wdnewslettersubscribe->checkRegisteredUser($this->request->post)) {
				   
					$this->model_account_wdnewslettersubscribe->UpdateRegisterUsers($this->request->post,1);
					
					echo('$("'.$prefix_eval.' #notification").html("<div class=\"success\"> '.$this->language->get('subscribe').'</div>");$("'.$prefix_eval.' #subscribe")[0].reset();');
				   
				
			   } else if (!$this->model_account_wdnewslettersubscribe->checkmailid($this->request->post)) {
				 
					$this->model_account_wdnewslettersubscribe->subscribe($this->request->post);
					
					echo('$("'.$prefix_eval.' #notification").html("<div class=\"success\"> '.$this->language->get('subscribe').'</div>");$("'.$prefix_eval.' #subscribe")[0].reset();');
				 
					if ($this->config->get('wdnewslettersubscribe_mail_status')) {
				   
						$subject = $this->language->get('mail_subject');	
						
						$message = '<table width="60%" cellpadding="2"  cellspacing="1" border="0"> 
									 <tr>
									   <td> Email Id </td>
									   <td> '.$this->request->post['subscribe_pemail'].' </td>
									 </tr>
									 <tr>
									   <td> Name  </td>
									   <td> '.$this->request->post['subscribe_pname'].' </td>
									 </tr>';
						$message .= '</table>';
			 
						$mail = new Mail();
						$mail->protocol = $this->config->get('config_mail_protocol');
						$mail->parameter = $this->config->get('config_mail_parameter');
						$mail->hostname = $this->config->get('config_smtp_host');
						$mail->username = $this->config->get('config_smtp_username');
						$mail->password = $this->config->get('config_smtp_password');
						$mail->port = $this->config->get('config_smtp_port');
						$mail->timeout = $this->config->get('config_smtp_timeout');				
						$mail->setTo($this->config->get('config_email'));
						$mail->setFrom($this->config->get('config_email'));
						$mail->setSender($this->config->get('config_name'));
						$mail->setSubject($subject);
						$mail->setHtml($message);
						$mail->send();
					}
				 
				} else {
					  
					  echo('$("'.$prefix_eval.' #notification").html("<div class=\"warning\"> '.$this->language->get('alreadyexist').'</div>");$("'.$prefix_eval.' #subscribe")[0].reset();');
					  
				}
			   
			} else {
				
				echo('$("'.$prefix_eval.' #notification").html("<div class=\"warning\"> '.$this->language->get('error_invalid').'</div>")');
				
			}
	  
	}

	public function unsubscribe(){
	  
		if ($this->config->get('newslettersubscribe_thickbox')) {
			  $prefix_eval = "";
		} else {
			  $prefix_eval = "";
		}
	  
		$this->language->load('extension/module/wdnewslettersubscribe');
	 
		$this->load->model('account/wdnewslettersubscribe');
	  
		if (isset($this->request->post['subscribe_email']) and filter_var($this->request->post['subscribe_email'],FILTER_VALIDATE_EMAIL)) {
				
			if ($this->config->get('wdnewslettersubscribe_registered') and $this->model_account_wdnewslettersubscribe->checkRegisteredUser($this->request->post)) {
				   
					$this->model_account_wdnewslettersubscribe->UpdateRegisterUsers($this->request->post,0);
					
					echo('$("'.$prefix_eval.' #notification").html("<div class=\"success\"> '.$this->language->get('unsubscribe').'</div>");$("'.$prefix_eval.' #subscribe")[0].reset();');
				   
				
			} else if (!$this->model_account_wdnewslettersubscribe->checkmailid($this->request->post)) {
				 
					echo('$("'.$prefix_eval.' #notification").html("<div class=\"warning\"> '.$this->language->get('notexist').'</div>");$("'.$prefix_eval.' #subscribe")[0].reset();');
				 
			} else {
				   
					if ($this->config->get('option_unsubscribe')) {
						
					 $this->model_account_wdnewslettersubscribe->unsubscribe($this->request->post);
					 
					 echo('$("'.$prefix_eval.' #notification").html("<div class=\"success\"> '.$this->language->get('unsubscribe').'</div>");$("'.$prefix_eval.' #subscribe")[0].reset();');
					 
					}
			}
			   
		} else {
			
				echo('$("'.$prefix_eval.' #notification").html("<div class=\"warning\"> '.$this->language->get('error_invalid').'</div>")');
				
		}
	  
	}

	protected function loadmodule() {
		
		$this->language->load('extension/module/wdnewslettersubscribe');

      	$data['heading_title'] = $this->language->get('heading_title');	
		
      	$data['entry_name'] = $this->language->get('entry_name');	
      	$data['entry_email'] = $this->language->get('entry_email');	
		$data['entry_button'] = $this->language->get('entry_button');	
		$data['entry_unbutton'] = $this->language->get('entry_unbutton');	
		$data['option_unsubscribe'] = $this->config->get('option_unsubscribe');	
		$data['thickbox'] = $this->config->get('newslettersubscribe_thickbox');	
		
		$data['text_subscribe'] = $this->language->get('text_subscribe');	
		
		$this->id = 'wdnewslettersubscribe';

		
		return $this->load->view('extension/module/wdnewslettersubscribe', $data);
	}
}
?>