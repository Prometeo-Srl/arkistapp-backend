<?php

/**
 * Default archive of a business workspace: the sections ("Sezioni e Categorie App")
 * become categories, everything nested under them becomes folders.
 *
 * Materialised by App\Observers\CompanyObserver when a Business company is created.
 *
 * Shape: a string entry is a leaf folder, a key => array entry is a folder with
 * children. Order in this file is the folder position.
 */
return [

    'Informazioni società' => [
        'Visura camerale',
        'Scia di inizio attività',
        'Documento di valutazione dei rischi',
    ],

    'Luogo di lavoro' => [
        'Locali' => [
            'Certificato di agibilità locali ai sensi articolo 24 del Testo Unico dell’Edilizia (DPR 380/2001)',
            'Deroga ASL locali sotterranei ai sensi dell’art. 65 D.Lgs 81/2008',
            'Certificazione antiscivolamento dei pavimenti dei bagni (Scuole)',
            'Certificazioni superfici vetrate UNI EN 12600',
            'Documento di valutazione dei rischi Microclima',
            'Scaffalature (aziendali)' => [
                'Cartello di portata (Foto)',
                'Dichiarazione di prestazione',
                'Manuale d’uso e manutenzione',
                'Documentazione di validazione periodica',
                'Verbali di controllo PRSES',
            ],
            'Area giochi (Scuole)' => [
                'Certificazioni EN 1177 per tappeti antitrauma',
                'Certificazioni EN 1176 attrezzature ludiche',
                'Certificazioni UNI 11123 area giochi',
            ],
        ],
        'Rischio strutturale/sismico' => [
            'Certificato di collaudo statico ai sensi della Legge 5 novembre 1971 n. 1086',
            'Certificato di idoneità statica ai sensi art. 24 D.P.R. 380/2001 e ai sensi art. 35 comma 19 Legge n. 47/85 e DM LL.PP 15/5/1985',
            'Relazione di verifica sismica ai sensi art. 2, comma 3, dell’OPCM 3274/2003',
        ],
    ],

    'Impianti' => [
        'Rischio ascensore/montacarichi' => [
            'Dichiarazione di conformità ex D.M. 37/08',
            'Comunicazione di messa in servizio dell’impianto',
            'Libretto impianto',
            'Rapportino verifica manutenzione impianto (Foto)',
            'Verbale di verifica biennale da parte di Organismo di verifica abilitato ai sensi del D.P.R. 162/1999 e successivo D.P.R. 214/2010',
        ],
        'Impianto elettrico' => [
            'Dichiarazione di conformità ai sensi dell’art. 7 comma 6 del DM 37/2008',
            'Dichiarazione di rispondenza ai sensi dell’art. 7 comma 6 del DM 37/2008',
            'Fascicolo tecnico dell’impianto',
            'Progetto esecutivo dell’impianto',
            'Registro dei controlli dell’impianto elettrico previsto dall’art. 86 D.Lgs 81/08',
        ],
        'Impianto di messa a terra' => [
            'Prima denuncia all’ATS e Inail territorialmente competente ai sensi del D.P.R. 462/2001',
            'Dichiarazione di conformità ex D.M. 37/08 dell’impianto di messa a terra',
            'Verbale di verifica periodica dell’impianto biennale/quinquennale da parte di Organismo di verifica abilitato',
        ],
        'Impianto di protezione contro le scariche atmosferiche' => [
            'Relazione di valutazione del rischio di fulminazione redatta ai sensi della norma tecnica CEI 62305-2020',
            'Verbale di verifica periodica dell’impianto ai sensi del D.P.R. 462/2001 da parte di Organismo di verifica abilitato',
            'Prima denuncia Inail territorialmente competente ai sensi del D.P.R. 462/2001',
        ],
        'Gruppo elettrogeno' => [
            'Dichiarazione di conformità ex D.M. 37/2008',
            'Certificato prevenzione incendi/Scia prevenzione incendi D.P.R. 151/2011 attività n. 49',
            'Certificazione conformità CE',
        ],
        'Impianto di riscaldamento e produzione acqua calda' => [
            'Dichiarazione di conformità ex D.M. 37/08',
            'Progetto dell’impianto',
            'Denuncia Inail per potenza impianto superiore a 35 kW',
            'Libretto dell’impianto rilasciato dall’Inail',
            'Scia/Certificato prevenzione incendi ai sensi del D.P.R. 151/2011 per attività n. 74 superiore a 116 kW',
            'Verifiche periodiche quinquennali impianto di riscaldamento da parte di ATS',
            'Verbale di manutenzione periodica impianto (se > 116 kW)',
        ],
        'Impianto di raffrescamento e condizionamento' => [
            'Dichiarazione di conformità ex D.M. 37/08',
            'Progetto impianto',
            'Verbale di manutenzione',
        ],
    ],

    'Rischio incendio' => [
        'Certificato prevenzione incendi' => [
            'Esito dei sopralluoghi e delle verifiche effettuate',
            'Documentazione progettuale' => [
                'Relazione tecnica',
                'Elaborati grafici',
                'Dichiarazione di non aggravio del rischio incendio',
            ],
            'Istanza di valutazione del progetto' => [
                'PIN 1-2023 Valutazione Progetto (in vigore dal 1° marzo 2023)',
                'PIN 1-2023 PNRR (dal 3 luglio 2023 – solo per attività PNRR, PNC, ZES)',
            ],
            'Segnalazione certificata di inizio attività' => [
                'Segnalazione certificata di inizio attività' => [
                    'PIN 1-2023 Valutazione Progetto (in vigore dal 1° marzo 2023)',
                    'PIN 1-2023 PNRR (dal 3 luglio 2023 – solo per attività PNRR, PNC, ZES)',
                ],
                'Asseverazione ai fini della sicurezza antincendio' => [
                    'PIN 2.1-2018 Asseverazione',
                ],
                'Certificazione di resistenza al fuoco' => [
                    'PIN 2.2-2023 – Cert. REI (in vigore dal 1° marzo 2023)',
                ],
                'Dichiarazione inerente i prodotti' => [
                    'PIN 2.2-2023 – Cert. REI (in vigore dal 1° marzo 2023)',
                ],
                'Dichiarazione di corretta installazione e funzionamento dell’impianto' => [
                    'PIN 2.4-2018 – Dich. Imp.',
                ],
                'Certificazione di rispondenza e di corretto funzionamento dell’impianto' => [
                    'PIN 2.5-2018 – Cert. Imp.',
                ],
                'Dichiarazione di non aggravio del rischio incendio' => [
                    'PIN 2.6-2018 Dichiarazione non aggravio rischio',
                ],
                'Segnalazione certificata di inizio attività per depositi di GPL' => [
                    'PIN 2 gpl-2018 S.C.I.A.',
                ],
                'Attestazione per depositi di GPL' => [
                    'PIN 2.1-gpl-2018 Attestazione',
                ],
                'Dichiarazione di installazione per depositi di GPL' => [
                    'PIN 2.7-gpl-2012 Dichiarazione di installazione',
                ],
                'Decreto 22 gennaio 2008 M.S.E.' => [
                    'Dichiarazione di rispondenza',
                ],
            ],
            'Rinnovo periodico di conformità antincendio' => [
                'Attestazione di rinnovo periodico di conformità antincendio' => [
                    'PIN 3-2023 Rinnovo periodico (in vigore dal 1° marzo 2023)',
                ],
                'Asseverazione ai fini della attestazione di rinnovo periodico di conformità' => [
                    'PIN 3.1-2014 Asseverazione per rinnovo',
                ],
                'Attestazione di rinnovo periodico di conformità antincendio per depositi di GPL' => [
                    'PIN 3-gpl-2018 Attestazione di rinnovo periodico gpl',
                ],
                'Dichiarazione per depositi di GPL' => [
                    'PIN 3.1-gpl-2018 Dichiarazione per rinnovo',
                ],
            ],
            'Deroga' => [
                'Istanza di deroga' => [
                    'PIN 4-2023 Deroga (in vigore dal 1° marzo 2023)',
                ],
            ],
            'Nulla osta di fattibilità' => [
                'Istanza di nulla osta di fattibilità' => [
                    'PIN 5-2023 Richiesta N.O.F. (in vigore dal 1° marzo 2023)',
                ],
            ],
            'Verifiche in corso d’opera' => [
                'Istanza di verifiche in corso d’opera' => [
                    'PIN 6-2018 Richiesta verifica in corso d’opera',
                ],
            ],
            'Voltura' => [
                'Dichiarazione per voltura' => [
                    'PIN 7-2018 Voltura',
                ],
            ],
            'Modulistica commercializzazione prodotti' => [
                'Richiesta di omologazione di porte resistenti al fuoco',
                'Richiesta di benestare per i sipari di sicurezza',
                'Autorizzazione dei laboratori di prova ai sensi del D.M. 26.03.1985',
                'Richiesta omologazione estintori portatili',
                'Rinnovo omologazione estintori portatili',
                'Certificato di prova estintori portatili',
                'Rapporto di prova estintori portatili',
            ],
        ],
        'Registro dei controlli prevenzione incendi',
        'Contratto ditta qualificata per controllo estinguenti',
        'Impianto idrico antincendio' => [
            'Dichiarazione di conformità',
            'Ultimo verbale di prova positiva impianto',
            'Contratto con ditta qualificata',
            'Verbale verifica portata e pressione impianto',
        ],
        'Impianto di rivelazione e allarme antincendio' => [
            'Dichiarazione di conformità',
            'Ultimo verbale di prova positiva',
            'Contratto ditta qualificata manutenzione semestrale',
            'Verbale di manutenzione semestrale',
        ],
        'Illuminazione di emergenza' => [
            'Contratto di manutenzione con ditta qualificata',
            'Verbale di manutenzione',
        ],
        'Porte tagliafuoco' => [
            'Dichiarazione di conformità ex D.M. 37/08',
            'Contratto di manutenzione con ditta qualificata',
            'Verbale di manutenzione',
        ],
    ],

    'Emergenze' => [
        'Piano di emergenza',
        'Planimetrie di evacuazione',
        'Verbali prove di evacuazione',
        'Certificato antisfondamento vetri porte di emergenza',
        'Libretto uso e manutenzione DAE',
        'Verbale di manutenzione periodica DAE',
    ],

    'Primo soccorso' => [
        'Elenco prodotti e scadenze cassetta primo soccorso',
    ],

    'Rischi specifici' => [
        'Rischio formazione atmosfere esplosive' => [
            'DVR rischio ATEX',
        ],
        'Rischio radon' => [
            'DVR rischio radon',
            'Relazione laboratorio',
        ],
        'Rischio impianto ossigeno medicale' => [
            'Dichiarazione di conformità',
            'Contratto ditta qualificata per manutenzione',
        ],
        'Rischio attrezzature munite di videoterminale' => [
            'Questionario VDT',
            'DVR rischio videoterminale',
        ],
        'Rischio movimentazione manuale dei carichi' => [
            'DVR movimentazione manuale dei carichi',
            'DVR MAPO (movimentazione ospiti)',
            'DVR movimenti ripetitivi arti superiori',
        ],
        'Rischio rumore' => [
            'DVR esposizione rumore',
        ],
        'Rischio vibrazioni' => [
            'DVR rischio vibrazioni',
        ],
        'Rischio esposizione campi elettromagnetici' => [
            'DVR campi elettromagnetici',
        ],
        'Rischio ROA' => [
            'DVR ROA',
        ],
        'Rischio esposizione agenti chimici' => [
            'Elenco sostanze pericolose utilizzate',
            'DVR rischio chimico',
        ],
        'Rischio esposizione agenti biologici' => [
            'Analisi controllo annuale legionella',
            'Protocollo controllo legionella',
            'Protocollo Covid-19',
        ],
        'Rischio ferite da taglio e da punta' => [
            'Procedure di utilizzo',
            'Opuscoli informativi',
        ],
        'Rischio lavori elettrici' => [
            'Protocolli e procedure',
        ],
        'Rischio lavoro notturno' => [
            'Protocolli e procedure',
        ],
        'Rischio lavori in quota' => [
            'Protocolli e procedure',
        ],
        'Rischio lavori isolati e solitari' => [
            'Protocolli e procedure',
        ],
        'Rischio lavori in ambienti confinati' => [
            'Protocolli e procedure',
        ],
        'Rischio stress lavoro-correlato' => [
            'Questionario strumento indicatore',
            'DVR stress lavoro-correlato',
        ],
        'Rischio connesso alla differenza di genere, età e provenienza da altri paesi' => [
            'Protocolli e procedure',
        ],
        'Rischio aggressione' => [
            'Protocolli e procedure',
        ],
        'Rischio alcol e sostanze stupefacenti' => [
            'Circolare informativa/Opuscoli',
            'Protocolli e procedure',
        ],
        'Rischio lavoratrici gestanti/puerpere' => [
            'Protocolli e procedure',
        ],
        'Rischio lavoratori minori' => [
            'Protocolli e procedure',
        ],
        'Rischio lavoratori tirocinanti/alunni in PCTO' => [
            'Elenco lavoratori',
            'Attestati di formazione',
        ],
        'Rischio lavoro all’estero' => [
            'Elenco lavoratori all’estero',
        ],
    ],

    'Servizio di prevenzione e protezione' => [
        'Datore di lavoro' => [
            'Lettera di incarico',
            'Attestato di formazione',
        ],
        'Dirigenti' => [
            'Lettera di incarico',
            'Attestato di formazione',
        ],
        'Preposti' => [
            'Lettera di incarico',
            'Attestato di formazione',
        ],
        'RSPP' => [
            'Lettera di incarico',
            'Attestato di formazione',
        ],
        'ASPP' => [
            'Lettera di incarico',
            'Attestato di formazione',
        ],
        'Medico competente' => [
            'Lettera di incarico',
        ],
        'RLS' => [
            'Verbale di elezione',
            'Attestato di formazione',
        ],
        'Addetti antincendio' => [
            'Lettera di incarico',
            'Attestato di formazione',
        ],
        'Addetti primo soccorso' => [
            'Lettera di incarico',
            'Attestato di formazione',
        ],
    ],

    'Misure organizzative e gestionali per la sicurezza' => [
        'Verbale riunione periodica',
        'Copia protocollo sorveglianza sanitaria',
        'Opuscoli informativi/Circolari interne' => [
            'DPI',
            'Elenco DPI',
            'Lettera di consegna DPI',
        ],
        'Contratti di appalto/d’opera',
        'Impresa 1/Libero professionista 1' => [
            'Copia certificato di iscrizione alla CCIAA',
            'DURC',
            'Autocertificazione firmata dal DDL della idoneità tecnico-professionale',
            'POS o DVR',
        ],
        'DUVRI',
    ],

    'Attrezzature di lavoro' => [
        'Automezzi aziendali' => [
            'Libretto',
        ],
        'Apparecchi di sollevamento' => [
            'Attestati abilitazione',
            'Immatricolazione INAIL',
            'Verbale di verifica periodica',
        ],
        'Apparecchi a pressione' => [
            'Immatricolazione INAIL',
            'Verbale di verifica periodica',
        ],
        'Carrelli elevatori' => [
            'Attestati di formazione',
        ],
        'Apparecchi elettromedicali' => [
            'Verbale di verifica periodica',
        ],
        'Registro manutenzione attrezzature di lavoro',
    ],

    'Sicurezza cantieri' => [
        'Certificato iscrizione camera di commercio (6 mesi)',
        'DURC valido',
        'Copia conforme libro matricola (per gli assunti entro il 31/12/2008) o comunicazione telematica di assunzione',
        'Giudizi di idoneità alla mansione dei lavoratori rilasciati dal MC',
        'Attestati formazione lavoratori',
        'Attestati formazione carrellisti',
        'Attestati formazione addetti prevenzione incendi e primo soccorso',
        'Libretti uso e manutenzione macchine e attrezzature',
        'Attestati formazione operatori montaggio e smontaggio ponteggi',
        'Libretto istruzioni uso e montaggio trabattelli',
        'Libretti omologazione e verbale verifica periodica' => [
            'Mezzi di sollevamento portata',
            'Carrelli semoventi braccio telescopico',
            'Piattaforme lavoro autosollevanti su colonna',
            'Ascensori e montacarichi da cantiere con cabina/piattaforma guidata verticalmente',
        ],
        'Registro verifiche trimestrali funi e catene',
        'Attestati formazione operatori mezzi di sollevamento',
        'Schede sicurezza preparati chimici',
        'DVR esposizione rumore',
        'DVR esposizione vibrazioni meccaniche',
        'Assicurazione RCT e RCO',
        'Verifiche periodiche estintori',
        'Verbale messa a disposizione DPI lavoratori',
        'Documenti per ogni cantiere' => [
            'Copia del PSC (redatto dal coordinatore sicurezza)',
            'Copia della notifica preliminare e degli aggiornamenti',
            'POS impresa',
            'Elenco personale di cantiere',
            'Richiesta e autorizzazione al subappalto rilasciata dal committente',
            'Contratti di subappalto',
            'POS subappaltatori',
            'Dichiarazione verifica congruenza POS subappaltatore rispetto al proprio e trasmissione al CSE',
            'Trasmissione e dichiarazione di presa visione del PSC da parte dei subappaltatori',
            'Piani di sicurezza per attività particolari' => [
                'Piano di lavoro per rimozione/bonifica amianto',
                'Piano lavori in quota su fune',
                'Piano demolizioni estese',
                'Piano di montaggio di capannoni prefabbricati in c.a.',
            ],
            'PIMUS ponteggi installati',
            'Copia autorizzazione ministeriale ponteggi installati',
            'Libretto uso ponteggi installati',
            'Progetto del ponteggio',
            'Disegno esecutivo del ponteggio',
            'Verbali riunioni di coordinamento',
            'Verbali sopralluogo in cantiere',
        ],
    ],
];
