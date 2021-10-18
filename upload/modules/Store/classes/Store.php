<?php
/*
 *	Made by Partydragen
 *  https://partydragen.com/resources/resource/5-store-module/
 *  https://partydragen.com/
 *
 *  License: MIT
 *
 *  Store module
 */

class Store {
    private $_db,
            $_cache,
            $_store_language;

    // Constructor, connect to database
    public function __construct($cache, $store_language) {
        $this->_db = DB::getInstance();
        
        $this->_cache = $cache;
        $this->_store_language = $store_language;
    }
    
    public function getStoreURL() {
        // Get variables from cache
        $this->_cache->setCache('store_settings');
        if($this->_cache->isCached('store_url')){
            $store_url = Output::getClean(rtrim($this->_cache->retrieve('store_url'), '/'));
        } else {
            $store_url = '/store';
        }
        
        return $store_url;
    }
	
	// Get all payments
	public function getAllPayments() {
		$payments = $this->_db->query('SELECT nl2_store_payments.*, uuid, username FROM nl2_store_payments LEFT JOIN nl2_store_players ON player_id=nl2_store_players.id ORDER BY created DESC')->results();
		
		return $payments;
	}
	
	// Get all categories
	public function getAllCategories() {
		$categories = $this->_db->query('SELECT * FROM nl2_store_categories WHERE deleted = 0 ORDER BY `order` ASC')->results();
			
		$categories_array = array();
		foreach($categories as $category){
			$categories_array[] = array(
				'id' => Output::getClean($category->id),
				'name' => Output::getClean($category->name)
			);
		}
		
		return $categories_array;
	}
    
    // Get navbar menu
    public function getNavbarMenu($active) {
        $store_url = $this->getStoreURL();
        $categories = array();
        
        $categories[] = array(
            'url' => URL::build($store_url),
            'title' => $this->_store_language->get('general', 'home'),
            'active' => Output::getClean($active) == 'Home'
        );
        
        $categories_query = DB::getInstance()->query('SELECT * FROM nl2_store_categories WHERE parent_category IS NULL AND deleted = 0 ORDER BY `order` ASC')->results();
        if(count($categories_query)){
            foreach($categories_query as $item){
                $subcategories_query = DB::getInstance()->query('SELECT id, `name` FROM nl2_store_categories WHERE parent_category = ? AND deleted = 0 ORDER BY `order` ASC', array($item->id))->results();

                $subcategories = array();
                $sub_active = false;
                if(count($subcategories_query)){
                    foreach($subcategories_query as $subcategory){
                        $sub_active = Output::getClean($active) == Output::getClean($subcategory->name);

                        $subcategories[] = array(
                            'url' => URL::build($store_url . '/category/' . Output::getClean($subcategory->id)),
                            'title' => Output::getClean($subcategory->name),
                            'active' => $sub_active
                        );
                    }
                }

                $categories[$item->id] = array(
                    'url' => URL::build($store_url . '/category/' . Output::getClean($item->id)),
                    'title' => Output::getClean($item->name),
                    'subcategories' => $subcategories,
                    'active' => !$sub_active && Output::getClean($active) == Output::getClean($item->name)
                );
            }
        }
        
        return $categories;
    }
    
    // Add pending commands
    public function addPendingCommands($player_id, $payment_id, $type) {
        $packages = $this->_db->query('SELECT * FROM nl2_store_payments_packages INNER JOIN nl2_store_packages ON nl2_store_packages.id=package_id WHERE payment_id = ?', array($payment_id))->results();
        foreach($packages as $package) {
            $commands = $this->_db->query('SELECT * FROM nl2_store_packages_commands WHERE package_id = ? AND type = ? ORDER BY `order`', array($package->id, $type))->results();
            foreach($commands as $command) {
                $this->_db->insert('store_pending_commands', array(
                    'payment_id' => $payment_id,
                    'player_id' => $player_id,
                    'server_id' => $command->server_id,
                    'type' => $command->type,
                    'command' => $command->command,
                    'require_online' => $command->require_online,
                    'order' => $command->order,
                ));
            }
        }
    }
    
    public function deletePendingCommands($payment_id) {
        $this->_db->createQuery('DELETE FROM nl2_store_pending_commands WHERE payment_id = ? AND status = 0', array($payment_id))->results();
    }
    
    /*
     *  Check for Module updates
     *  Returns JSON object with information about any updates
     */
    public static function updateCheck($current_version = null) {
        $queries = new Queries();

        // Check for updates
        if (!$current_version) {
            $current_version = $queries->getWhere('settings', array('name', '=', 'nameless_version'));
            $current_version = $current_version[0]->value;
        }

        $uid = $queries->getWhere('settings', array('name', '=', 'unique_id'));
        $uid = $uid[0]->value;
		
		$enabled_modules = Module::getModules();
		foreach($enabled_modules as $enabled_item){
			if($enabled_item->getName() == 'Store'){
				$module = $enabled_item;
				break;
			}
		}
		

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL, 'https://api.partydragen.com/stats.php?uid=' . $uid . '&version=' . $current_version . '&module=Store&module_version='.$module->getVersion() . '&domain='. Util::getSelfURL());

        $update_check = curl_exec($ch);
        curl_close($ch);

		$info = json_decode($update_check);
		if (isset($info->message)) {
			die($info->message);
		}
		
        return $update_check;
    }
}