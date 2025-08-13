<?php
if (!defined('_TB_VERSION_')) {
    exit;
}

class BankwireAccount extends ObjectModel
{
    /** @var int */
    public $id_bankwire_account;
    /** @var int */
    public $id_currency;
    /** @var string */
    public $owner;
    /** @var string */
    public $details;
    /** @var string */
    public $address;

    public static $definition = [
        'table' => 'bankwire_account',
        'primary' => 'id_bankwire_account',
        'multilang' => true,
        'multishop' => true,
        'fields' => [
            'id_currency' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'owner' => ['type' => self::TYPE_STRING, 'lang' => true, 'validate' => 'isGenericName', 'required' => true, 'size' => 255],
            'details' => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isCleanHtml', 'required' => true],
            'address' => ['type' => self::TYPE_HTML, 'lang' => true, 'validate' => 'isCleanHtml', 'required' => true],
        ],
    ];

    /**
     * Get account for currency and shop
     *
     * @param int $idCurrency
     * @param int $idShop
     * @param int|null $idLang
     *
     * @return array|false
     */
    public static function getByCurrency($idCurrency, $idShop, $idLang = null)
    {
        $idLang = $idLang ?: (int) Context::getContext()->language->id;
        $sql = 'SELECT a.id_bankwire_account, al.owner, al.details, al.address
                FROM '._DB_PREFIX_.'bankwire_account a
                INNER JOIN '._DB_PREFIX_.'bankwire_account_shop s ON (a.id_bankwire_account = s.id_bankwire_account AND s.id_shop='.(int) $idShop.')
                INNER JOIN '._DB_PREFIX_.'bankwire_account_lang al ON (a.id_bankwire_account = al.id_bankwire_account AND al.id_lang='.(int) $idLang.')
                WHERE a.id_currency='.(int) $idCurrency.' LIMIT 1';
        return Db::getInstance()->getRow($sql);
    }

    /**
     * Get currencies with accounts for a shop
     *
     * @param int $idShop
     *
     * @return array
     */
    public static function getCurrenciesByShop($idShop)
    {
        $sql = 'SELECT DISTINCT a.id_currency FROM '._DB_PREFIX_.'bankwire_account a
                INNER JOIN '._DB_PREFIX_.'bankwire_account_shop s ON (a.id_bankwire_account = s.id_bankwire_account AND s.id_shop='.(int) $idShop.')';
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Check if an account exists for currency and shop
     *
     * @param int $idCurrency
     * @param int $idShop
     * @param int $exclude
     *
     * @return bool
     */
    public static function existsForCurrency($idCurrency, $idShop, $exclude = 0)
    {
        $sql = 'SELECT a.id_bankwire_account FROM '._DB_PREFIX_.'bankwire_account a
                INNER JOIN '._DB_PREFIX_.'bankwire_account_shop s ON (a.id_bankwire_account = s.id_bankwire_account AND s.id_shop='.(int) $idShop.')
                WHERE a.id_currency='.(int) $idCurrency;
        if ($exclude) {
            $sql .= ' AND a.id_bankwire_account!='.(int) $exclude;
        }
        return (bool) Db::getInstance()->getValue($sql);
    }
}
