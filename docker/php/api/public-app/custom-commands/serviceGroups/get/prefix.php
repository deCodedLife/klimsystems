<?php

if ( !$requestData->sort_by ) {

    $requestData->sort_by = "title";
    $requestData->sort_order = "asc";

}

$requestSettings[ "filter" ][ "is_active" ] = "Y";