<?php

namespace OnaOnbir\OOAutoWeave\Enums;

enum NodeStatus: string
{
    case Queued = 'queued';     // İşlem sırası bekliyor
    case Processing = 'processing'; // Şu an çalışıyor
    case Completed = 'completed';  // Başarıyla tamamlandı
    case Failed = 'failed';     // Beklenmeyen hata oldu (örn. istisna)
    case Skipped = 'skipped';    // Atlama override'ı geldi
    case Retrying = 'retrying';
}
