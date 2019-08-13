<?php

ini_set("display_errors",1);
error_reporting(E_ALL);

class Send24_Shipping_Model_Carrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface {

    protected $_code = 'send24_shipping';
    public $select_denmark = 'Denmark';
    public $price_denmark = 0;
    public $price_international = 0;
    public $postcode = 1560;

    public $product_id_express = 7062;
    public $product_id_danmark = 6026;
    
    public $is_available_international = false;

    public function getFormBlock(){
		return 'send24_shipping/pickup';
	}

    public function collectRates(Mage_Shipping_Model_Rate_Request $request) {
        $result = Mage::getModel('shipping/rate_result');
        // Check default currency.
        if(Mage::app()->getStore()->getDefaultCurrencyCode() == 'DKK'){
	        // Express.
	        $result->append($this->_getExpressShippingRate());

	        // Denmark.
	        $enable_denmark = $this->getConfigData('enable_denmark');
		    if($enable_denmark == 1){
	       		$result->append($this->_getDenmarkShippingRate());
	       	}  

	       	// International.
	        $enable_international = $this->getConfigData('enable_international');
		    if($enable_international == 1){
	       		$result->append($this->_getInternationalShippingRate());
	       	}
	    }
        
        return $result;
    }

    // NEDD TEST.
    public function adminSystemConfigChangedSectionCarriers()
    {
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
		curl_setopt($ch, CURLOPT_URL, "https://www.send24.com/wc-api/v3/get_user_id");
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
		curl_close($ch);

		// Check key or secret.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://www.send24.com/wc-api/v3/get_service_area/".$this->postcode);
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

    
    public function toOptionArray()
    {
        return array(
          array(
            'value' => '0',
            'label' => '1000kr'
          ),
          array(
            'value' => '1',
            'label' => '2000kr'
          ),
          array(
            'value' => '2',
            'label' => '3000kr'
          ),
          array(
            'value' => '3',
            'label' => '4000kr'
          ),
          array(
            'value' => '4',
            'label' => '5000kr'
          ),
        );
    }


    public function after_order_placed($observer) {
        $incrementId = $observer->getOrder()->getIncrementId();
        // DK.
        $country = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountryId();
        $postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();
        $send24_consumer_key = $this->getConfigData('send24_consumer_key');
        $send24_consumer_secret = $this->getConfigData('send24_consumer_secret');
        $current_shipping_method = $observer->getOrder()->getShippingMethod();
        $select_country = 'Ekspres';
		$shipping_country_code = Mage::getModel('directory/country')->loadByCode(Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry());
    	$shipping_country_name = $shipping_country_code->getName();

        // get/check Express.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.send24.com/wc-api/v3/get_products");
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
		                $cost = $send24_countries[$i]->price;
		                $send24_product_id = $send24_countries[$i]->product_id;               
		                $i = $n;
		                $is_available_express = true;
		            }else{ 
		                $is_available_express = false;
		            }
				break;

				case 'send24_shipping_send24':
					if ($send24_countries[$i]->product_id == $this->product_id_danmark)
					{
						$this->price_denmark = $send24_countries[$i]->price;    
		                $is_available_denmark = true;
					}
				break;

				case 'send24_shipping_international':
					if ($send24_countries[$i]->title == $shipping_country_name && $shipping_country_name != $this->select_denmark)
					{
						$this->price_international = $send24_countries[$i]->price;
		                $international_product_id = $send24_countries[$i]->product_id;               
		                $this->is_available_international = true;
					}
				break;
        	}
        }

        switch ($current_shipping_method){
			case 'send24_shipping_express':
				if($is_available_express == true){
		            $insurance_price = 0;
		            $discount = "false";
		            $ship_total = $type = $price_need = '';

		            $user_id = $observer->getOrder()->getCustomerId();
		            $shipping_data = $observer->getOrder()->getShippingAddress()->getData();
		            $billing_data = $observer->getOrder()->getBillingAddress()->getData();

		            if($select_country == 'Ekspres'){ $select_country = 'Danmark'; $where_shop_id = 'ekspres'; }
		 
		            // Create order.
		            $ch = curl_init();
		            curl_setopt($ch, CURLOPT_URL, "https://www.send24.com/wc-api/v3/create_order");
		            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		            curl_setopt($ch, CURLOPT_HEADER, FALSE);
		            curl_setopt($ch, CURLOPT_POST, TRUE);
		            curl_setopt($ch, CURLOPT_POSTFIELDS, '
		                                            {
		                                            "TO_company": "'.$shipping_data['company'].'",
		                                            "TO_first_name": "'.$shipping_data['firstname'].'",
		                                            "TO_last_name": "'.$shipping_data['lastname'].'",
		                                            "TO_phone": "'.$shipping_data['telephone'].'",
		                                            "TO_email": "'.$shipping_data['email'].'",
		                                            "TO_country": "'.$select_country.'",
		                                            "TO_city": "'.$shipping_data['city'].'",
		                                            "TO_postcode": "'.$postcode.'",
		                                            "Insurance" : "'.$insurance_price.'",
		                                            "Weight": "5",
		                                            "TO_address": "'.$shipping_data['street'].'",
		                                            "WHAT_product_id": "'.$send24_product_id.'",
		                                            "WHERE_shop_id": "'.$where_shop_id.'",
		                                            "discount": "'.$discount.'",
		                                            "type": "'.$type.'",
		                                            "need_points": "'.$price_need.'",
		                                            "total": "'.$ship_total.'",
		                                            "ship_mail": "'.$shipping_data['email'].'",
		                                            "bill_mail": "'.$billing_data['email'].'"
		                                            }
		                                            ');
					curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
		            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		               	"Content-Type: application/json",
		            ));
		            $response = curl_exec($ch);
		            curl_close($ch);
		        }
			break;
			case 'send24_shipping_send24':
				if($is_available_denmark == true){
	        		$insurance_price = 0;
		            $discount = "false";
		            $type = $price_need = '';

		            $user_id = $observer->getOrder()->getCustomerId();
		            $shipping_data = $observer->getOrder()->getShippingAddress()->getData();
		            $billing_data = $observer->getOrder()->getBillingAddress()->getData();

		            $select_country = 'Danmark';
		            $selected_shop_id = Mage::getModel('core/cookie')->get('selected_shop_id'); 
		            if(!empty($selected_shop_id)){
		           		$where_shop_id = $selected_shop_id; 
		            }else{
		            	$where_shop_id = '';
		            }
	 
		            // Create order.
		            $ch = curl_init();
		            curl_setopt($ch, CURLOPT_URL, "https://www.send24.com/wc-api/v3/create_order");
		            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		            curl_setopt($ch, CURLOPT_HEADER, FALSE);
		            curl_setopt($ch, CURLOPT_POST, TRUE);
		            curl_setopt($ch, CURLOPT_POSTFIELDS, '
		                                            {
		                                            "TO_company": "'.$shipping_data['company'].'",
		                                            "TO_first_name": "'.$shipping_data['firstname'].'",
		                                            "TO_last_name": "'.$shipping_data['lastname'].'",
		                                            "TO_phone": "'.$shipping_data['telephone'].'",
		                                            "TO_email": "'.$shipping_data['email'].'",
		                                            "TO_country": "'.$select_country.'",
		                                            "TO_city": "'.$shipping_data['city'].'",
		                                            "TO_postcode": "'.$postcode.'",
		                                            "Insurance" : "'.$insurance_price.'",
		                                            "Weight": "5",
		                                            "TO_address": "'.$shipping_data['street'].'",
		                                            "WHAT_product_id": "'.$this->product_id_danmark.'",
		                                            "WHERE_shop_id": "'.$where_shop_id.'",
		                                            "discount": "'.$discount.'",
		                                            "type": "'.$type.'",
		                                            "need_points": "'.$price_need.'",
		                                            "total": "'.$this->price_denmark .'",
		                                            "ship_mail": "'.$shipping_data['email'].'",
		                                            "bill_mail": "'.$billing_data['email'].'"
		                                            }
		                                            ');
					
					curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
		            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		               	"Content-Type: application/json",
		            ));

		            $response = curl_exec($ch);
		            curl_close($ch);
	        	}
			break;	
			case 'send24_shipping_international':
				if($this->is_available_international == true){
	        		$insurance_price = 0;
		            $discount = "false";
		            $ship_total = $type = $price_need = '';

		            $user_id = $observer->getOrder()->getCustomerId();
		            $shipping_data = $observer->getOrder()->getShippingAddress()->getData();
		            $billing_data = $observer->getOrder()->getBillingAddress()->getData();

		            $where_shop_id = ''; 
		 
		            // Create order.
		            $ch = curl_init();
		            curl_setopt($ch, CURLOPT_URL, "https://www.send24.com/wc-api/v3/create_order");
		            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		            curl_setopt($ch, CURLOPT_HEADER, FALSE);
		            curl_setopt($ch, CURLOPT_POST, TRUE);
		            curl_setopt($ch, CURLOPT_POSTFIELDS, '
		                                            {
		                                            "TO_company": "'.$shipping_data['company'].'",
		                                            "TO_first_name": "'.$shipping_data['firstname'].'",
		                                            "TO_last_name": "'.$shipping_data['lastname'].'",
		                                            "TO_phone": "'.$shipping_data['telephone'].'",
		                                            "TO_email": "'.$shipping_data['email'].'",
		                                            "TO_country": "'.$shipping_country_name.'",
		                                            "TO_city": "'.$shipping_data['city'].'",
		                                            "TO_postcode": "'.$postcode.'",
		                                            "Insurance" : "'.$insurance_price.'",
		                                            "Weight": "5",
		                                            "TO_address": "'.$shipping_data['street'].'",
		                                            "WHAT_product_id": "'.$international_product_id.'",
		                                            "WHERE_shop_id": "'.$where_shop_id.'",
		                                            "discount": "'.$discount.'",
		                                            "type": "'.$type.'",
		                                            "need_points": "'.$price_need.'",
		                                            "total": "'.$this->price_international .'",
		                                            "ship_mail": "'.$shipping_data['email'].'",
		                                            "bill_mail": "'.$billing_data['email'].'"
		                                            }
		                                            ');
					curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
		            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		               	"Content-Type: application/json",
		            ));

		            $response = curl_exec($ch);
		            curl_close($ch);
	        	}
			break;
		}
        	
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

        $observer->getOrder()->save();
        return true;
    }

    // Express Send24.
    protected function _getExpressShippingRate() {
        $rate = Mage::getModel('shipping/rate_result_method');
        // DK
        $country = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountryId();
        $postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();

        $send24_consumer_key = $this->getConfigData('send24_consumer_key');
        $send24_consumer_secret = $this->getConfigData('send24_consumer_secret');
		$config_payment_parcels = $this->getConfigData('payment_parcels');
		$shipping_country_code = Mage::getModel('directory/country')->loadByCode(Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry());
        $shipping_country_name = $shipping_country_code->getName();
        $select_country = 'Ekspres';

        // Get/check Express.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.send24.com/wc-api/v3/get_products");
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
                curl_setopt($ch, CURLOPT_URL, "https://www.send24.com/wc-api/v3/get_user_id");
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
                $full_billing_address = "$billing_address_1, $billing_postcode $billing_city, $billing_country";
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
                        curl_setopt($ch, CURLOPT_URL, "https://www.send24.com/wc-api/v3/get_is_driver_area_five_km");
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
                       // print_r($full_billing_address);
                        return $rate;

                    }
                }
            }
        }
           // die;

    }

    // Denmark Send24.
    protected function _getDenmarkShippingRate() {
        // DK
        $shipping_postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();
        $send24_consumer_key = $this->getConfigData('send24_consumer_key');
        $send24_consumer_secret = $this->getConfigData('send24_consumer_secret');
		$config_payment_parcels = $this->getConfigData('payment_parcels');
		$shipping_country_code = Mage::getModel('directory/country')->loadByCode(Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry());
        $shipping_country_name = $shipping_country_code->getName();

        // Check zip.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://www.send24.com/wc-api/v3/get_service_area/".$shipping_postcode);
		curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type: application/json"
			));
		$zip_area = curl_exec($ch);
		curl_close($ch);
		if($zip_area == 'true'){
	        // Get/check Denmark.
	        $ch = curl_init();
	        curl_setopt($ch, CURLOPT_URL, "https://www.send24.com/wc-api/v3/get_products");
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	        curl_setopt($ch, CURLOPT_HEADER, FALSE);
	        curl_setopt($ch, CURLOPT_USERPWD, $send24_consumer_key . ":" . $send24_consumer_secret);
	        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	            "Content-Type: application/json"
	            ));
	        $send24_countries = json_decode(curl_exec($ch));
	        // print_r($send24_countries);


	        curl_close($ch);
	        // Check errors.
	        if(empty($send24_countries->errors)){
	            $n = count($send24_countries);
	           	if($shipping_country_name == $this->select_denmark || $shipping_country_name == 'Danmark'){
		            for ($i = 0; $i < $n; $i++)
		            {
		                // Denmark.
			            if ($send24_countries[$i]->product_id == $this->product_id_danmark )
						{
							// Insurance.
							// $insurance_price = $this->getConfigData('select_insurance');
							$this->price_denmark = $send24_countries[$i]->price;
		                    $is_available = true;
		                    break;
						}else{
		                    $is_available = false;
						}
	            	}
				}
	
	            if($is_available == true){
			    	$rate = Mage::getModel('shipping/rate_result_method');
			        $rate->setCarrier($this->_code);
			        $rate->setCarrierTitle($this->getConfigData('title'));
			        $rate->setMethod('send24');
			        $rate->setMethodTitle('Send24 - ');
			    	if($config_payment_parcels == 1){ 
			        	// Payment shop. 
			        	$this->price_denmark = 0;
			        }
			        $rate->setPrice($this->price_denmark);
			        $rate->setCost(0);
			        return $rate;
	            }
	        }
	    }
    }


    protected function _getInternationalShippingRate(){
        // International.
        $shipping_postcode = Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getPostcode();
        $shipping_country_code = Mage::getModel('directory/country')->loadByCode(Mage::getSingleton('checkout/type_onepage')->getQuote()->getShippingAddress()->getCountry());
        $shipping_country_name = $shipping_country_code->getName();
        $send24_consumer_key = $this->getConfigData('send24_consumer_key');
        $send24_consumer_secret = $this->getConfigData('send24_consumer_secret');
		$config_payment_parcels = $this->getConfigData('payment_parcels');
		if($shipping_country_name == $this->select_denmark){
			$shipping_country_name = 'noname';
		}

        // Get/check Denmark.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://www.send24.com/wc-api/v3/get_products");
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
			// Check on no Denmark.
        	$is_available_international = false;
            if($shipping_country_name != $this->select_denmark && $shipping_country_name != 'Danmark'){
	            for ($i = 0; $i < $n; $i++)
	            {
	                // International.
		            if ($send24_countries[$i]->title == $shipping_country_name)
					{
						$this->price_international = $send24_countries[$i]->price;
	                    $is_available_international = true;
	                    break;
					}else{
	                    $is_available_international = false;
					}
				}
			}	
        }

        if($is_available_international == true){
	    	$rate = Mage::getModel('shipping/rate_result_method');
	        $rate->setCarrier($this->_code);
	        $rate->setCarrierTitle($this->getConfigData('title'));
	        $rate->setMethod('international');
	        $rate->setMethodTitle('Send24 - ');
	    	if($config_payment_parcels == 1){ 
	        	// Payment shop. 
	        	$this->price_international = 0;
	        }
	        $rate->setPrice($this->price_international);
	        $rate->setCost(0);
	        return $rate;
        }
	    
	}
     
    public function getAllowedMethods() {
        return array(
            'send24' => 'Send24',
            'express' => 'Send24 Sameday Solution',
            'international' => 'Send24 International',
        );
    }

}
