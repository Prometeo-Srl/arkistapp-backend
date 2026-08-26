<?php

namespace App\Enums;

/**
 * "gestisci accesso" of one document (prototype 204): who reaches it, next to
 * whatever the folder around it already hands out.
 */
enum FileVisibility: string
{
    /** "esteso": tutti gli utenti con accesso in cartella — the default. */
    case Inherited = 'inherited';

    /** "privato": solo io. The folder's grants stop at the document. */
    case Private = 'private';

    /** "personalizzato": proprietario e utenti selezionati, granted on the file itself. */
    case Custom = 'custom';
}
