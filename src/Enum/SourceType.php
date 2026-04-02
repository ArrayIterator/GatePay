<?php
declare(strict_types=1);

namespace ArrayIterator\GatePay\Enum;

enum SourceType: string
{
    case JSON = 'json';

    case XML = 'xml';

    case TEXT = 'text';

    case HTML = 'html';

    case CSV = 'csv';

    case YAML = 'yaml';

    case FORM = 'form';

    case MULTIPART = 'multipart';

    case URLENCODED = 'urlencoded';

    case RAW = 'raw';

    case BINARY = 'binary';

    case OTHER = 'other';
}
