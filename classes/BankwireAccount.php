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
    
    const CURRENCY_ALL = 0;

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

        $q = new DbQuery();
        $q->select('a.id_bankwire_account, al.owner, al.details, al.address');
        $q->from('bankwire_account', 'a');
        $q->innerJoin('bankwire_account_shop', 's',
            'a.id_bankwire_account = s.id_bankwire_account AND s.id_shop='.(int)$idShop);
        $q->innerJoin('bankwire_account_lang', 'al',
            'a.id_bankwire_account = al.id_bankwire_account AND al.id_lang='.(int)$idLang);
        // prefer specific currency; fall back to ALL (0)
        $q->where('a.id_currency IN ('.(int)$idCurrency.', 0)');
        $q->orderBy('a.id_currency = 0 ASC'); // specific first, then ALL

        return Db::getInstance()->getRow($q); // LIMIT 1 is added automatically
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
        $sql = 'SELECT DISTINCT a.id_currency
                FROM '._DB_PREFIX_.'bankwire_account a
                INNER JOIN '._DB_PREFIX_.'bankwire_account_shop s
                    ON (a.id_bankwire_account = s.id_bankwire_account AND s.id_shop='.(int)$idShop.')';
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
        $q = new DbQuery();
        $q->select('a.id_bankwire_account');
        $q->from('bankwire_account', 'a');
        $q->innerJoin('bankwire_account_shop', 's',
            'a.id_bankwire_account = s.id_bankwire_account AND s.id_shop='.(int)$idShop);
        if ((int)$idCurrency === self::CURRENCY_ALL) {
            $q->where('a.id_currency = 0');                 // only block duplicate ALL per shop
        } else {
            $q->where('a.id_currency = '.(int)$idCurrency);  // one per currency per shop
        }
        if ($exclude) {
            $q->where('a.id_bankwire_account != '.(int)$exclude);
        }
        return (bool) Db::getInstance()->getValue($q);
    }
}
