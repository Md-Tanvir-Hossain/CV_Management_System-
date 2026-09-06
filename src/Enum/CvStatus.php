<?php

namespace App\Enum;

enum CvStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}