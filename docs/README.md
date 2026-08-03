# Documentazione Prometeo

| File | A chi serve | Cosa contiene |
|---|---|---|
| `er-model.html` | Cliente, nuovi sviluppatori | Modello ER illustrato: un diagramma per dominio, campi chiave, regole e punti aperti. |
| `er-model.md` | Sviluppatori | Riferimento completo: ogni entità con tutti i campi, vincoli trasversali, stato di implementazione. |
| `Prometeo.pdf` | — | Capitolato tecnico originale (Allegato 1 – Piano delle Attività). Fonte, non modificare. |

`er-model.html` è la sorgente della pagina pubblicata, non un file autonomo: i diagrammi
sono blocchi Mermaid renderizzati da chi ospita la pagina, quindi aprendolo da disco si
vede il testo dei diagrammi e non il disegno. Per leggerlo come lo vede il cliente serve
la versione pubblicata; per aggiornarlo si modifica il testo del diagramma, non un'immagine.
`er-model.md` invece si legge nudo: i suoi blocchi Mermaid li rendono GitHub e gli IDE.

Pagina pubblicata: https://claude.ai/code/artifact/22853e10-2a2c-4d0d-a4b9-b54200ef661f

Le altre due fonti del modello sono i prototipi XD:

- azienda — https://xd.adobe.com/view/3f0926e3-d024-4d99-b0b1-d67bfc5be0af-6a5a/grid
- lavoratore — https://xd.adobe.com/view/488bdfc2-6a3e-4a6b-8210-f0786cdb3854-1dd8/grid
