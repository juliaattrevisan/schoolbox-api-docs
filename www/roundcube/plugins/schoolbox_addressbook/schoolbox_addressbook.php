<?php

require_once(dirname(__FILE__) . '/schoolbox_addressbook_backend.php');

class schoolbox_addressbook extends rcube_plugin {
    private $address_book_id = 'schoolbox';

    public function init() {
        $this->add_hook('addressbooks_list', array($this, 'address_sources'));
        $this->add_hook('addressbook_get', array($this, 'get_address_book'));

        // use this address book for autocompletion queries
        // (maybe this should be configurable by the user?)
        $config = rcmail::get_instance()->config;
        $sources = (array) $config->get('autocomplete_addressbooks', array('sql'));
        
        if (!in_array($this->address_book_id, $sources)) {
            $sources[] = $this->address_book_id;
            $config->set('autocomplete_addressbooks', $sources);
        }
    }

    public function address_sources($p) {
        $address_book = new schoolbox_addressbook_backend;
        
        $p['sources'][$this->address_book_id] = array(
            'id'       => $this->address_book_id,
            'name'     => 'Public Directory',
            'readonly' => $address_book->readonly,
            'groups'   => $address_book->groups,
        );
        
        return $p;
    }

    public function get_address_book($address_book) {
        if ($address_book['id'] === $this->address_book_id) {
            $address_book['instance'] = new schoolbox_addressbook_backend;
        }

        return $address_book;
    }

}
