public function index() {
    include_once(DIR_SYSTEM . 'library/simple/simplecheckout.php');

    $simple = \SimpleCheckout::getInstance($this->registry, 'default');

    if (!$this->customer->isLogged() && $simple->isGuestCheckoutDisabled()) {
        echo 'Гість не може оформити';
        return;
    }

    echo 'SimpleCheckout повністю працює';
}
