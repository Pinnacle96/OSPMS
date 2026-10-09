<?php

namespace App\Domains\Enforcement\Enums;

enum InspectionResult: string
{
    case Compliant = 'compliant';
    case NonCompliant = 'non_compliant';
    case RequiresReview = 'requires_review';
}
