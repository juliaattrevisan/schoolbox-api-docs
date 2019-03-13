<?php
/**
 * Created by Steve Chan.
 *
 * User: Steve
 * Date: 28/07/11
 * Time: 11:30 AM
 *
 * Twitter_Loader_Interface
 *
 * Interface to which all TwitterLoaders must adhere
 */
interface Alaress_Twitter_Loader_Interface {

    /**
     * Load results. Preprocess them to get rid of unwanted data and to format
     * the return value in a uniform structure.
     *
     * @param null|string $resource
     * @param null|array  $params
     * @return array
     */
    public function load(?string $resource, ?array $params = null): array;
}
