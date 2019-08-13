<?php

ini_set("display_errors",1);
error_reporting(E_ALL);

class Send24_Shipping_Model_Carrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {

    protected $_code = 'send24_shipping';
    public $select_denmark = 'Denmark';
    public $price = 0;
    public $postcode = 1560;
    public $product_id_express = 7062;
    

    public function getFormBlock(){
		return 'send24_shipping/pickup';
	}

    public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
        $result = Mage::getModel('shipping/rate_result');
        // Check default currency.
        if(Mage::app()->getStore()->getDefaultCurrencyCode() == 'DKK'){
	        // Express.
	        $result->append($this->_getExpressShippingRate());
	        // Countries.
	        $enable_denmark = $this->getConfigData('enable_denmark');
		    if($enable_denmark == 1){
		    	// Get key.
		    	$send24_consumer_key = $this->getConfigData('send24_consumer_key');
	        	$send24_consumer_secret = $this->getConfigData('send24_consumer_secret');
	        	// Weight.
		    	$quote = Mage::getSingleton('checkout/session')->getQuote();
				$weight = $quote->getShippingAddress()->getWeight();
				// Get/check Country.
		        $ch = curl_init();
		        curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_countries");
		        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		        curl_setopt($ch, CURLOPT_HEADER, FALSE);
		        curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
		        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		            "Content-Type: application/json"
		            ));
		        $send24_countries = json_decode(curl_exec($ch));
		        $shipping_country_code = Mage::getModel('directory/country')->loadByCode(Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry());
	 			$is_available = false;
		        if(!empty($send24_countries['0'])){
		        	foreach ($send24_countries['0'] as $key => $value) {
		        		$value = (array)$value;
		        		if($value['code'] == 'DK'){
		        			if($weight <= 5 && !empty($value['0_5_kg'])){
		        				$price_denmark = $value['0_5_kg'];
		        			}elseif($weight > 5 && $weight <= 10 && !empty($value['5_10_kg'])){
		        				$price_denmark = $value['5_10_kg'];
		        			}elseif($weight > 10 && $weight <= 15 && !empty($value['10_15_kg'])){
		        				$price_denmark = $value['10_15_kg'];
		        			}
		        		}
		       		}
		        }
		        if(empty($price_denmark)){
		        	 $price_denmark = false;
		        }
	       	}  

	       	// Destributions.
	       	$active_smartprice = $this->getConfigData('active_smartprice');
	       	if($active_smartprice == 1){
				if($price_denmark != false){
					$price[] = $price_denmark;
				}
				$price[] = $this->getConfigData('active_bring_price');
				$price[] = $this->getConfigData('active_dhl_price');
				$price[] = $this->getConfigData('active_gls_price');
	       		$price[] = $this->getConfigData('active_postdanmark_price');
				$price[] = $this->getConfigData('active_tnt_price');
				$price[] = $this->getConfigData('active_ups_price');
				$array_price = array_diff($price, array(''));
				$key_show = array_keys($array_price, min($array_price));
				switch ($key_show[0]) {
					case 0: 
					    if($enable_denmark == 1){
				       		$result->append($this->_getDenmarkShippingRate());
				       	}
				       	break;
					case 1:
				       	$result->append($this->_getBringShippingRate());
						break;
					case 2:
				       	$result->append($this->_getDHLShippingRate());
						break;				
					case 3:
		       			$result->append($this->_getGLSShippingRate());
						break;				
					case 4:
				       	$result->append($this->_getPostDenamrkShippingRate());
						break;				
					case 5:
				       	$result->append($this->_getTNTShippingRate());
						break;
					case 6:
				       	$result->append($this->_getUPSShippingRate());
						break;				
				}
	       	}else{
				// Denmark Send24.
			    if($enable_denmark == 1){
		       		$result->append($this->_getDenmarkShippingRate());
		       	}  
		       	// Destributions.
				$result->append($this->_getBringShippingRate());
				$result->append($this->_getDHLShippingRate());
	       		$result->append($this->_getGLSShippingRate());
				$result->append($this->_getPostDenamrkShippingRate());
				$result->append($this->_getTNTShippingRate());
				$result->append($this->_getUPSShippingRate());
	       	}
	    }
        return $result;
    }

    public function adminSystemConfigChangedSectionCarriers()
    {
		$get_model =  Mage::getStoreConfig('carriers/send24_shipping/model');
		if($get_model == 'send24_shipping/carrier'){
	    	// Save return link.
	    	$send24_consumer_key = $this->getConfigData('send24_consumer_key');
	        $send24_consumer_secret = $this->getConfigData('send24_consumer_secret');

	        $version = (float)Mage::getVersion();
			$new_file = $_SERVER['DOCUMENT_ROOT'].'/app/design/adminhtml/default/default/template/send24/sales/order/view/info.phtml';
	        if(!file_exists($new_file)) {
				if($version < 1.5){
					try {
						$file = $_SERVER['DOCUMENT_ROOT'].'/app/design/adminhtml/default/default/template/send24/sales/order/view/info1_4.phtml';
	                    copy($file, $new_file);
					 }catch(Exception $error){
						 Mage::getSingleton('core/session')->addError($error->getMessage());
						 return false;
					 }
				}else{
					try {
						$file = $_SERVER['DOCUMENT_ROOT'].'/app/design/adminhtml/default/default/template/send24/sales/order/view/info1_9.phtml';
	                    copy($file, $new_file);
					 }catch(Exception $error){
						 Mage::getSingleton('core/session')->addError($error->getMessage());
						 return false;
					 }
				}
			}

			// Save return.
	      	$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_user_id");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);
			curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Content-Type: application/json",
				)
			);
			$user_meta = json_decode(curl_exec($ch));
			if(!empty($user_meta->return_activate)){
				$result_return = $user_meta->return_webpage_link['0'];
	        	Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/return_portal', $result_return);
			}else{
				$result_return = ' ';
	        	Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/return_portal', $result_return);
			}
			// Distributor. 
			$distributor_active_PostDanmark = $user_meta->distributor_active_PostDanmark[0];
	        $distributor_active_GLS = $user_meta->distributor_active_GLS[0];
	        $distributor_active_UPS = $user_meta->distributor_active_UPS[0];
	        $distributor_active_DHL = $user_meta->distributor_active_DHL[0];
	        $distributor_active_TNT = $user_meta->distributor_active_TNT[0];
	        $distributor_active_Bring = $user_meta->distributor_active_Bring[0];
	        if(!empty($distributor_active_PostDanmark)){
	         	Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/active_postdanmark', '1');
	        }else{
	        	Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/active_postdanmark', '0');
	        }
	        if(!empty($distributor_active_GLS)){
	           Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/active_gls', '1');
	        }else{
	        	Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/active_gls', '0');
	        }
	        if(!empty($distributor_active_UPS)){
	           Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/active_ups', '1');
	        }else{
	           Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/active_ups', '0');
	        }
	        if(!empty($distributor_active_DHL)){
	           Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/active_dhl', '1');
	        }else{
	        	Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/active_dhl', '0');
	        }
	        if(!empty($distributor_active_TNT)){
	           Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/active_tnt', '1');
	        }else{
	           Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/active_tnt', '0');
	        }
	        if(!empty($distributor_active_Bring)){
	           Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/active_bring', '1');
	        }else{
	           Mage::getModel('core/config')->saveConfig('carriers/send24_shipping/active_bring', '0');
	        }
			curl_close($ch);

			// Check key or secret.
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_service_area/".$this->postcode);
			curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Content-Type: application/json"
				));
			$zip_area = curl_exec($ch);
			if($zip_area == 'true'){
				Mage::getSingleton('core/session')->addSuccess('Key and secret passed authorization on send24.com successfully.');
			}else{
				Mage::getSingleton('core/session')->addError('Key or secret incorrect.');
			}
			curl_close($ch);

			// Refresh magento configuration cache.
	  		Mage::app()->getCacheInstance()->cleanType('config');
	  	}
		
    }

    public function after_order_placed($observer) {
        $incrementId = $observer->getOrder()->getIncrementId();
        // DK.
        $country = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountryId();
        $postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();
        $send24_consumer_key = $this->getConfigData('send24_consumer_key');
        $send24_consumer_secret = $this->getConfigData('send24_consumer_secret');
        $current_shipping_method = $observer->getOrder()->getShippingMethod();
		$shipping_country_code = Mage::getModel('directory/country')->loadByCode(Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry());
    	$shipping_country_name = $shipping_country_code->getName();
      	$country_id = $shipping_country_code->getData('country_id');
      	// Address.
		$shipping_address_1 = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getData('street');
        $shipping_postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();
        $shipping_city = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCity();
        $shipping_country = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry();
        $full_shipping_address = "$shipping_address_1, $shipping_postcode $shipping_city, $shipping_country";
        // Get shipping coordinates.
        $shipping_url = "http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=".urlencode($full_shipping_address);
        $shipping_latlng = get_object_vars(json_decode(file_get_contents($shipping_url)));
        // Weight.
        $quote = Mage::getSingleton('checkout/session')->getQuote();
		$weight = $quote->getShippingAddress()->getWeight();

        // Check shipping address.
        if(!empty($shipping_latlng['results'])){
	    	if($current_shipping_method == 'send24_shipping_express'){
		        // get/check Express.
		        $ch = curl_init();
		        curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_products");
		        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		        curl_setopt($ch, CURLOPT_HEADER, FALSE);
		        curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
		        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		            "Content-Type: application/json"
		            ));
		        $send24_countries = json_decode(curl_exec($ch));
		        curl_close($ch);
		        $n = count($send24_countries);
		        $is_available_denmark = false;
		        for ($i = 0; $i < $n; $i++)
		        {
		        	switch ($current_shipping_method){
						case 'send24_shipping_express':
							if ($send24_countries[$i]->product_id == $this->product_id_express)
				            {   
				                $this->price = $send24_countries[$i]->price;
				                $send24_product_id = $send24_countries[$i]->product_id;               
				                $is_available = true;
				            }else{ 
				                $is_available = false;
				            }
						break;
		        	}
		        }
	    	}else{
	    		// Get/check Country.
		        $ch = curl_init();
		        curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_countries");
		        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		        curl_setopt($ch, CURLOPT_HEADER, FALSE);
		        curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
		        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		            "Content-Type: application/json"
		            ));
		        $send24_countries = json_decode(curl_exec($ch));
		        $shipping_country_code = Mage::getModel('directory/country')->loadByCode(Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry());
	 			$is_available = false;
		        if(!empty($send24_countries['0'])){
		        	foreach ($send24_countries['0'] as $key => $value) {
		        		$value = (array)$value;
		        		if($value['code'] == $country_id){
		        			if($weight <= 5 && !empty($value['0_5_kg'])){
		        				$this->price = $value['0_5_kg'];
		        			}elseif($weight > 5 && $weight <= 10 && !empty($value['5_10_kg'])){
		        				$this->price = $value['5_10_kg'];
		        			}elseif($weight > 10 && $weight <= 15 && !empty($value['10_15_kg'])){
		        				$this->price = $value['10_15_kg'];
		        			}
		                    $is_available = true;
		                    break;
		        		}
		       		}
		        }

		        if(empty($this->price)){
		        	 $is_available = false;
		        }
	    	}

	    	$current_shipping_method = explode('send24_shipping_', $current_shipping_method);
	    	$product_code = 's24p';
	    	if(!empty($current_shipping_method['1'])){
		    	switch ($current_shipping_method['1']) {
		    		case 'send24':
		    		 	$distributor_name = 'Send24';
 	                 	break;		
		    		case 'express':
		    			$product_code = 's24s';
		    			$where_shop_id = 'ekspres';
		    			$distributor_name = '';
		    			break;
					case 'send24_postdenamrk':
						$distributor_name = 'PostDanmark';
		    			break;
					case 'send24_gls':
						$distributor_name = 'GLS';
		    			break;
					case 'send24_ups':
						$distributor_name = 'UPS';
		    			break;
					case 'send24_dhl':
						$distributor_name = 'DHL';
		    			break;
		    		case 'send24_tnt':
						$distributor_name = 'TNT';
		    			break;	    		
		    		case 'send24_bring':
						$distributor_name = 'Bring';
		    			break;
		    	}
	    	}

	    	// Selected shop.
	        $selected_shop_id = Mage::getModel('core/cookie')->get('selected_shop_id'); 
	        if(!empty($selected_shop_id) && $current_shipping_method['1'] == 'Send24'){
	       		$where_shop_id = $selected_shop_id; 
	        }else{
	        	$where_shop_id = '';
	        }

	        $user_id = $observer->getOrder()->getCustomerId();
	        $shipping_data = $observer->getOrder()->getShippingAddress()->getData();
	        $billing_data = $observer->getOrder()->getBillingAddress()->getData();

		    // Create order.
	        $ch = curl_init();
	        curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/create_order");
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	        curl_setopt($ch, CURLOPT_HEADER, FALSE);
	        curl_setopt($ch, CURLOPT_POST, TRUE);
	        curl_setopt($ch, CURLOPT_POSTFIELDS, '{
										      "company" : "'.$shipping_data['company'].'",
										      "first_name" : "'.$shipping_data['firstname'].'",
										      "last_name" : "'.$shipping_data['lastname'].'",
										      "phone" : "'.$shipping_data['telephone'].'",
										      "email" : "'.$shipping_data['email'].'",
										      "country_code" : "'.$country_id.'",
										      "city" : "'.$shipping_data['city'].'",
										      "postcode" : "'.$postcode.'",
										      "address" : "'.$shipping_data['street'].'",
										      "product_code" : "'.$product_code.'",
										      "shop_id" : "'.$where_shop_id.'",
										      "distributor_name" : "'.$distributor_name.'"
										    }');

			curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
	        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	           	"Content-Type: application/json",
	        ));
	        $response = curl_exec($ch);
	        curl_close($ch);
	   	
	        $response_order = json_decode($response, JSON_FORCE_OBJECT);
	        $version = (float)Mage::getVersion();
			if($version >= 1.5){
		        $history = Mage::getModel('sales/order_status_history')
		                            ->setStatus($observer->getOrder()->getStatus())
		                            ->setComment('<strong>Track parsel </strong><br><a href="'.$response_order['track'].'" target="_blank">'.$response_order['track'].'</a>')
		                            ->setEntityName(Mage_Sales_Model_Order::HISTORY_ENTITY_NAME)
		                            ->setIsCustomerNotified(false)
		                            ->setCreatedAt(date('Y-m-d H:i:s', time() - 60*60*24));

		        $observer->getOrder()->addStatusHistory($history);
	    	}
	        // Create custom value for order.
	        // it temporarily
	        require_once('app/Mage.php');
	        Mage::app()->setCurrentStore(Mage::getModel('core/store')->load(Mage_Core_Model_App::ADMIN_STORE_ID));
	        $installer = new Mage_Sales_Model_Mysql4_Setup;
	        $attribute_track_parsel  = array(
	            'type'          => 'varchar',
	            'backend_type'  => 'varchar',
	            'frontend_input' => 'varchar',
	            'is_user_defined' => true,
	            'label'         => 'Send24 Track Parsel',
	            'visible'       => false,
	            'required'      => false,
	            'user_defined'  => false,
	            'searchable'    => false,
	            'filterable'    => false,
	            'comparable'    => false,
	            'default'       => ''
	        );
	        $attribute_printout  = array(
	            'type'          => 'text',
	            'backend_type'  => 'text',
	            'frontend_input' => 'text',
	            'is_user_defined' => true,
	            'label'         => 'Send24 Printout',
	            'visible'       => false,
	            'required'      => false,
	            'user_defined'  => false,
	            'searchable'    => false,
	            'filterable'    => false,
	            'comparable'    => false,
	            'default'       => ''
	        );
	        $installer->addAttribute('order', 'send24_track_parsel', $attribute_track_parsel);
	        $installer->addAttribute('order', 'send24_printout', $attribute_printout);
	        $installer->endSetup();
	        // Add Track parsel.
	        $observer->getOrder()->setSend24TrackParsel($response_order['track']);
	        // Add Printout.
	        $printout = json_encode($response_order);
	        $observer->getOrder()->setSend24Printout($printout);

	        // Track notice
	        $config_track_notice = $this->getConfigData('track_notice');
	        if($config_track_notice == 1){ 	
				$emailTemplate = Mage::getModel('core/email_template')->loadDefault('send24_track_notice');
				// Getting the Store E-Mail Sender Name.
				$senderName = Mage::getStoreConfig('trans_email/ident_general/name');
				// Getting the Store General E-Mail.
				$senderEmail = Mage::getStoreConfig('trans_email/ident_general/email');

				//Variables for Confirmation Mail.
				$emailTemplateVariables = array();
				$emailTemplateVariables['track'] = $response_order['track'];
				$order_id = $observer->getOrder()->getId();
				$emailTemplateVariables['id'] = $order_id;

				//Appending the Custom Variables to Template.
				$processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables);
				$customerEmail = $shipping_data['email'];
				
				$version = (float)Mage::getVersion();
				if($version < 1.5){
					$headers  = 'MIME-Version: 1.0' . "\r\n";
					$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
					$subject = 'Subject: Send24 Track Notice';
					$message = 'Track: <a href="'.$emailTemplateVariables['track'].'">'.$emailTemplateVariables['track'].'</a>';
					mail($senderEmail, $subject, $message, $headers);
				}else{
					//Sending E-Mail to Customers.
					$mail = Mage::getModel('core/email')
					 ->setToName($senderName)
					 ->setToEmail($customerEmail)
					 ->setBody($processedTemplate)
					 ->setSubject('Subject: Send24 Track Notice')
					 ->setFromEmail($senderEmail)
					 ->setFromName($senderName)
					 ->setType('html');
					 try{
						 //Confimation E-Mail Send
						 $mail->send();
					 }catch(Exception $error){
						 Mage::getSingleton('core/session')->addError($error->getMessage());
						 return false;
					 }
				}
	        }
	    }

        $observer->getOrder()->save();
        return true;
    }

    // Express Send24.
    protected function _getExpressShippingRate() {
        $rate = Mage::getModel('shipping/rate_result_method');
        // DK
        $country = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountryId();
        $postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();
        // Key.
        $send24_consumer_key = $this->getConfigData('send24_consumer_key');
        $send24_consumer_secret = $this->getConfigData('send24_consumer_secret');
		$config_payment_parcels = $this->getConfigData('payment_parcels');
		$shipping_country_code = Mage::getModel('directory/country')->loadByCode(Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry());
        $shipping_country_name = $shipping_country_code->getName();

        // Get/check Express.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_products");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json"
            ));
        $send24_countries = json_decode(curl_exec($ch));
        curl_close($ch);
        // Check errors.
        if(empty($send24_countries->errors)){
            $n = count($send24_countries);
            if($shipping_country_name == $this->select_denmark  || $shipping_country_name == 'Danmark'){
            	for ($i = 0; $i < $n; $i++)
	            {
	            	// Express.
	                if ($send24_countries[$i]->product_id == $this->product_id_express)
	                {   
	                    $cost = $send24_countries[$i]->price;
	                    $product_id = $send24_countries[$i]->product_id;               
	                    $i = $n;
	                    $is_available = true;
	                }else{ 
	                    $is_available = false;
	                }
	            }
	        }

            if($is_available == true){
                $shipping_address_1 = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getData('street');
                $shipping_postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();
                $shipping_city = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCity();
                $shipping_country = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry();
                if($shipping_country == 'DK'){
                    $shipping_country = 'Denmark';
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_user_id");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    "Content-Type: application/json"
                    ));
                $user_meta = json_decode(curl_exec($ch));

                $billing_address_1 = $user_meta->billing_address_1['0'];
                $billing_postcode = $user_meta->billing_postcode['0'];
                $billing_city = $user_meta->billing_city['0'];
                $billing_country = $user_meta->billing_country['0'];
                if($billing_country == 'DK'){
                    $billing_country = 'Denmark';
                }

                $full_billing_address = "$billing_address_1, $billing_postcode $billing_city, $billing_country";
                $full_shipping_address = "$shipping_address_1, $shipping_postcode $shipping_city, $shipping_country";
                // $full_shipping_address = "Lermontova St, 26, Zaporizhzhia, Zaporiz'ka oblast, Ukraine";
                // $full_billing_address = "Lermontova St, 26, Zaporizhzhia, Zaporiz'ka oblast, Ukraine";

                // Get billing coordinates.
                $billing_url = "http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=".urlencode($full_billing_address);
                $billing_latlng = get_object_vars(json_decode(file_get_contents($billing_url)));
                // Check billing address.
                if(!empty($billing_latlng['results'])){
                    $billing_lat = $billing_latlng['results'][0]->geometry->location->lat;
                    $billing_lng = $billing_latlng['results'][0]->geometry->location->lng;

                    // Get shipping coordinates.
                    $shipping_url = "http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=".urlencode($full_shipping_address);
                    $shipping_latlng = get_object_vars(json_decode(file_get_contents($shipping_url)));
                    // Check shipping address.
                    if(!empty($shipping_latlng['results'])){
                        $shipping_lat = $shipping_latlng['results'][0]->geometry->location->lat;
                        $shipping_lng = $shipping_latlng['results'][0]->geometry->location->lng;

                        // get_is_driver_area_five_km
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_is_driver_area_five_km");
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                        curl_setopt($ch, CURLOPT_HEADER, FALSE);
                        curl_setopt($ch, CURLOPT_POST, TRUE);
                        curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, '
                                                        {
                                                            "billing_lat": "'.$billing_lat.'",
                                                            "billing_lng": "'.$billing_lng.'",
                                                            "shipping_lat": "'.$shipping_lat.'",
                                                            "shipping_lng": "'.$shipping_lng.'"
                                                        }
                                                        ');
                        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            "Content-Type: application/json"
                        ));

                        $response = curl_exec($ch);
                        //print_r($response);
                        $res = json_decode($response);
                        // Express (Sameday).
                        if(!empty($res)){
                            // Get time work Express.
                            $start_work_express = $this->getConfigData('startexpress_time_select');
                            $end_work_express = $this->getConfigData('endexpress_time_select');
                             // Check time work.
                            date_default_timezone_set('Europe/Copenhagen');
                            $today = strtotime(date("Y-m-d H:i"));
                            $replace_starttime = str_replace(",", ":", $start_work_express);
                            $replace_endtime = str_replace(",", ":", $end_work_express);
                            $start_time = strtotime(''.date("Y-m-d").' '.$replace_starttime.'');
                            $end_time = strtotime(''.date("Y-m-d").' '.$replace_endtime.'');
                            // Check time setting in plugin. 
                            if($start_time < $today && $end_time > $today){
                                // Check start_time.
                                if(!empty($res->start_time)){
                                    $picked_up_time = strtotime(''.date("Y-m-d").' '.$res->start_time.'');
                                    // Check time work from send24.com
                                    if($start_time < $picked_up_time && $end_time > $picked_up_time){
                                        $rate->setCarrier($this->_code);
                                        $rate->setCarrierTitle($this->getConfigData('title'));
                                        $rate->setMethod('express');
                                        $rate->setMethodTitle('Send24 Sameday(ETA: '.$res->end_time.') - ');
                                        // Who Payment.
								        if($config_payment_parcels == 1){ 
								        	// Payment shop. 
								        	$cost = 0;
								        }
                                        $rate->setPrice($cost);
                                        $rate->setCost(0);
                                    }
                                }
                            }
                        }
		                curl_close($ch);
                        return $rate;
                    }
                }
            }
        }
    }

    // Denmark Send24.
    protected function _getDenmarkShippingRate() {
        $shipping_postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();
        $send24_consumer_key = $this->getConfigData('send24_consumer_key');
        $send24_consumer_secret = $this->getConfigData('send24_consumer_secret');
		$config_payment_parcels = $this->getConfigData('payment_parcels');
		$shipping_country_code = Mage::getModel('directory/country')->loadByCode(Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry());
		// Address.
		$shipping_address_1 = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getData('street');
        $shipping_postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();
        $shipping_city = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCity();
        $shipping_country = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry();
        $full_shipping_address = "$shipping_address_1, $shipping_postcode $shipping_city, $shipping_country";
        // Get shipping coordinates.
        $shipping_url = "http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=".urlencode($full_shipping_address);
        $shipping_latlng = get_object_vars(json_decode(file_get_contents($shipping_url)));

        // Check shipping address.
        if(!empty($shipping_latlng['results'])){
	        // Check zip.
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_service_area/".$shipping_postcode);
			curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HEADER, FALSE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				"Content-Type: application/json"
				));
			$zip_area = curl_exec($ch);
			curl_close($ch);
			$quote = Mage::getSingleton('checkout/session')->getQuote();
			$weight = $quote->getShippingAddress()->getWeight();

			if($zip_area == 'true'){
		        // Get/check Denmark.
		        $ch = curl_init();
		        curl_setopt($ch, CURLOPT_URL, "https://send24.com/wc-api/v3/get_countries");
		        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		        curl_setopt($ch, CURLOPT_HEADER, FALSE);
		        curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
		        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		            "Content-Type: application/json"
		            ));
		        $send24_countries = json_decode(curl_exec($ch));
		        curl_close($ch);
		        $is_available = false;
		      	$country_id = $shipping_country_code->getData('country_id');
		        if(!empty($send24_countries['0'])){
		        	foreach ($send24_countries['0'] as $key => $value) {
		        		$value = (array)$value;
		        		if($value['code'] == $country_id){
		        			if($weight <= 5 && !empty($value['0_5_kg'])){
		        				$this->price = $value['0_5_kg'];
		        			}elseif($weight > 5 && $weight <= 10 && !empty($value['5_10_kg'])){
		        				$this->price = $value['5_10_kg'];
		        			}elseif($weight > 10 && $weight <= 15 && !empty($value['10_15_kg'])){
		        				$this->price = $value['10_15_kg'];
		        			}
		                    $is_available = true;
		                    break;
		        		}
		       		}
		        }

		        if(empty($this->price)){
		        	 $is_available = false;
		        }

	            if($is_available == true){
			    	$rate = Mage::getModel('shipping/rate_result_method');
			        $rate->setCarrier($this->_code);
			        $rate->setCarrierTitle($this->getConfigData('title'));
			        $rate->setMethod('send24');
			        $rate->setMethodTitle('Send24 - ');
			    	if($config_payment_parcels == 1){ 
			        	// Payment shop. 
			        	$this->price = 0;
			        }
			        $rate->setPrice($this->price);
			        $rate->setCost(0);
			        return $rate;
	            }
		    }
		}   
    }


	// PostDenmark.
	protected function _getPostDenamrkShippingRate(){
		$active_postdanmark = $this->getConfigData('active_postdanmark');
		$active_postdanmark_price = $this->getConfigData('active_postdanmark_price');
		$config_payment_parcels = $this->getConfigData('payment_parcels');
		if(!empty($active_postdanmark_price) || $active_postdanmark_price == '0' && $active_postdanmark == '1'){
			$rate = Mage::getModel('shipping/rate_result_method');
	        $rate->setCarrier($this->_code);
	        $rate->setCarrierTitle($this->getConfigData('title'));
	        $rate->setMethod('send24_postdenamrk');
	        $rate->setMethodTitle('PostDanmark');
	        $this->price = $active_postdanmark_price;
	    	if($config_payment_parcels == 1){ 
	        	// Payment shop. 
	        	$this->price = 0;
	        }
	        $rate->setPrice($this->price);
	        $rate->setCost(0);
	        return $rate;
	    }
	}

	// GLS.
	protected function _getGLSShippingRate(){
		$active = $this->getConfigData('active_gls');
		$price = $this->getConfigData('active_gls_price');
		$config_payment_parcels = $this->getConfigData('payment_parcels');
		if(!empty($price) || $price == '0' && $active == '1'){
			$rate = Mage::getModel('shipping/rate_result_method');
	        $rate->setCarrier($this->_code);
	        $rate->setCarrierTitle($this->getConfigData('title'));
	        $rate->setMethod('send24_gls');
	        $rate->setMethodTitle('GLS');
	        $this->price = $price;
	    	if($config_payment_parcels == 1){ 
	        	// Payment shop. 
	        	$this->price = 0;
	        }
	        $rate->setPrice($this->price);
	        $rate->setCost(0);
	        return $rate;
	    }
	}

	// UPS.
	protected function _getUPSShippingRate(){
		$active = $this->getConfigData('active_ups');
		$price = $this->getConfigData('active_ups_price');
		$config_payment_parcels = $this->getConfigData('payment_parcels');
		if(!empty($price) || $price == '0' && $active == '1'){
			$rate = Mage::getModel('shipping/rate_result_method');
	        $rate->setCarrier($this->_code);
	        $rate->setCarrierTitle($this->getConfigData('title'));
	        $rate->setMethod('send24_ups');
	        $rate->setMethodTitle('UPS');
	        $this->price = $price;
	    	if($config_payment_parcels == 1){ 
	        	// Payment shop. 
	        	$this->price = 0;
	        }
	        $rate->setPrice($this->price);
	        $rate->setCost(0);
	        return $rate;
	    }
	}

	// DHL.
	protected function _getDHLShippingRate(){
		$active = $this->getConfigData('active_dhl');
		$price = $this->getConfigData('active_dhl_price');
		$config_payment_parcels = $this->getConfigData('payment_parcels');
		if(!empty($price) || $price == '0' && $active == '1'){
			$rate = Mage::getModel('shipping/rate_result_method');
	        $rate->setCarrier($this->_code);
	        $rate->setCarrierTitle($this->getConfigData('title'));
	        $rate->setMethod('send24_dhl');
	        $rate->setMethodTitle('DHL');
	        $this->price = $price;
	    	if($config_payment_parcels == 1){ 
	        	// Payment shop. 
	        	$this->price = 0;
	        }
	        $rate->setPrice($this->price);
	        $rate->setCost(0);
	        return $rate;
	    }
	}

	// TNT.
	protected function _getTNTShippingRate(){
		$active = $this->getConfigData('active_tnt');
		$price = $this->getConfigData('active_tnt_price');
		$config_payment_parcels = $this->getConfigData('payment_parcels');
		if(!empty($price) || $price == '0' && $active == '1'){
			$rate = Mage::getModel('shipping/rate_result_method');
	        $rate->setCarrier($this->_code);
	        $rate->setCarrierTitle($this->getConfigData('title'));
	        $rate->setMethod('send24_tnt');
	        $rate->setMethodTitle('TNT');
	        $this->price = $price;
	    	if($config_payment_parcels == 1){ 
	        	// Payment shop. 
	        	$this->price = 0;
	        }
	        $rate->setPrice($this->price);
	        $rate->setCost(0);
	        return $rate;
	    }
	}

	// Bring.
	protected function _getBringShippingRate(){
		$active = $this->getConfigData('active_bring');
		$price = $this->getConfigData('active_bring_price');
		$config_payment_parcels = $this->getConfigData('payment_parcels');
		if(!empty($price) || $price == '0' && $active == '1'){
			$rate = Mage::getModel('shipping/rate_result_method');
	        $rate->setCarrier($this->_code);
	        $rate->setCarrierTitle($this->getConfigData('title'));
	        $rate->setMethod('send24_bring');
	        $rate->setMethodTitle('Bring');
	        $this->price = $price;
	    	if($config_payment_parcels == 1){ 
	        	// Payment shop. 
	        	$this->price = 0;
	        }
	        $rate->setPrice($this->price);
	        $rate->setCost(0);
	        return $rate;
	    }
	}
     
    public function getAllowedMethods() {
        return array(
            'send24' => 'Send24',
            'express' => 'Send24 Sameday Solution',
            'send24_postdenamrk' => 'PostDanmark',
            'send24_gls' => 'GLS',
            'send24_ups' => 'UPS',
            'send24_dhl' => 'DHL',
            'send24_tnt' => 'TNT',
            'send24_bring' => 'Bring',
        );
    }

}
