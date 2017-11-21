<?php
class shopPluginYaModel extends shopPluginModel
{
    /**
     *
     * List available plugins of specified type
     * @param string $type plugin type
     * @param array $options
     * @return array[]
     */
    public function listPlugins($type, $options = array())
    {
        waSystem::dieMod($this);
        $fields = array(
            'type' => $type,
        );
        if (empty($options['all'])) {
            $fields['status'] = 1;
        }
        $plugins = $this->getByField($fields, $this->id);
        $complementary = ($type == self::TYPE_PAYMENT) ? self::TYPE_SHIPPING : self::TYPE_PAYMENT;
        $non_available = array();
        if (!empty($options[$complementary])) {
            $non_available = shopHelper::getDisabledMethods($type, $options[$complementary]);
        }
        foreach ($plugins as & $plugin) {
            $plugin['available'] = !in_array($plugin['id'], $non_available);
        }
        unset($plugin);
        return $plugins;
    }
}
