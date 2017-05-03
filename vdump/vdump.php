<?php

function vdump($variable)
{
    echo nl2br(var_export($variable, true));
}