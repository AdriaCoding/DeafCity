# Transcription benchmark — local faster-whisper vs Groq API

**Host:** 2-core CPU, 8 GB RAM, no GPU, no swap.

**Timing caveat:** `local-fw-turbo-int8` is pure on-CPU inference. `groq-*` is full round-trip (file upload + network + cloud inference) — the real latency a Producer feels, not a pure inference number. RTF = wall time ÷ audio duration (lower is faster).

## Timings

| Sample | Lang | Engine | Audio (s) | Wall (s) | RTF | Cues | Error |
|---|---|---|---|---|---|---|---|
| ALGER_Mahieddine_6 | fr | local-fw-turbo-int8 | 56.8 | 55.8 | 0.98 | 8 | |
| ALGER_Mahieddine_6 | fr | groq-whisper-large-v3 | 56.8 | 0.9 | 0.02 | 9 | |
| ALGER_Mahieddine_6 | fr | groq-whisper-large-v3-turbo | 56.8 | 0.4 | 0.01 | 16 | |
| Roma_Serena_3 | it | local-fw-turbo-int8 | 45.9 | 58.5 | 1.28 | 9 | |
| Roma_Serena_3 | it | groq-whisper-large-v3 | 45.9 | 0.9 | 0.02 | 9 | |
| Roma_Serena_3 | it | groq-whisper-large-v3-turbo | 45.9 | 0.4 | 0.01 | 9 | |
| BCN_Raquel_3 | ca | local-fw-turbo-int8 | 108.5 | 190.9 | 1.76 | 36 | |
| BCN_Raquel_3 | ca | groq-whisper-large-v3 | 108.5 | 1.5 | 0.01 | 31 | |
| BCN_Raquel_3 | ca | groq-whisper-large-v3-turbo | 108.5 | 0.6 | 0.01 | 22 | |

## Transcripts side by side

### ALGER_Mahieddine_6

**local-fw-turbo-int8**

> Dans le passé, quand j'étais petit, ma grand-mère, je lui demandais, je lui disais, je rêve de parler un jour. Je suis sourd aujourd'hui, ça m'a triste. Ma grand-mère, elle réfléchit, attends, j'ai une idée, tu vas voir. Le mieux, on attend l'Aïd, donc le mouton. A la fête de l'Aïd, arrivé, on tape chez toutes les personnes, chez sept maisons différentes, et on récupère la langue des moutons. Voilà, l'une après l'autre, chez sept voisins. A la maison, on cuisine avec, on fait une recette spéciale, et on mange ça. En grandissant, j'ai appris à parler.

**groq-whisper-large-v3**

>  Dans le passé, quand j'étais petit, ma grand-mère, je lui demandais, je lui disais, je rêve de parler un jour. Je suis sourd aujourd'hui, ça m'attriste. Ma grand-mère, elle réfléchit, attends, j'ai une idée, tu vas voir. Le mieux, on attend l'Aïd, donc le mouton. A la fête de l'Aïd, arrivé, on tape chez toutes les personnes, chez sept maisons différentes. Et on récupère la langue du mouton. Voilà, l'une après l'autre, chez sept voisins. A la maison, on cuisine avec, on fait une recette spéciale et on mange ça. En grandissant, j'ai appris à parler.

**groq-whisper-large-v3-turbo**

>  Dans le passé, quand j'étais petit, ma grand-mère, je lui disais, je rêve de parler un jour. Je suis sourd aujourd'hui, ça m'a triste. Ma grand-mère, elle réfléchit, attends, j'ai une idée, tu vas voir. Le mieux, on attend l'Aïd, donc le mouton. à la fête de l'Aïd arrivé on tape chez toutes les personnes chez 7 maisons différentes et on récupère la langue de mouton voilà l'une après l'autre chez 7 voisins à la maison on cuisine avec on fait une recette spéciale et on mange ça en grandissant j'ai appris à parler

### Roma_Serena_3

**local-fw-turbo-int8**

> Nella carrozza di un treno c'era una donna vestita elegante, seduta con le gambe accavallate, un cappello a falda larga e le ciglia folte. Arrivarono i tre moschettieri che si sedutero accanto a lei. Alcune mosche iniziano ad infastidire la donna. Il moschettiere americano intervenne per primo, offrendo il suo aiuto. Sguinò la spada e tagliò in due una mosca in orizzontale e la donna lo ringraziò impressionata. L'italiano divertito intervenne per secondo e con la sua spada divise un'altra mosca in verticale, dall'alto al basso. Anche in questo caso la donna si complimentò stupita. Infine fu il turno del cinese che mosse la sua spada ma la mosca continuò a muoversi. Nessuno capì, ma poi guardando meglio si accorsero che la mosca si stava coprendo perché le aveva tagliato il pene.

**groq-whisper-large-v3**

>  Nella carrozza di un treno c'era una donna vestita elegante, seduta con le gambe accavallate, un cappello a falda larga e le ciglia folte. Arrivarono i tre moschettieri che si sedutero accanto a lei. Alcune mosche iniziarono ad infastidire la donna. Il moschettiere americano intervenne per primo, offrendo il suo aiuto. Sguenò la spada e tagliò in due una mosca, in orizzontale, e la donna lo ringraziò impressionata. L'italiano divertito intervenne per secondo e con la sua spada divise un'altra mosca in verticale, dall'alto al basso. Anche in questo caso la donna si complementò stupita. Infine fu il turno del cinese che mosse la sua spada ma la mosca continuò a muoversi. Nessuno capì, ma poi guardando meglio si accorsero che la mosca si stava coprendo perché le aveva tagliato il pene.

**groq-whisper-large-v3-turbo**

>  Nella carrozza di un treno c'era una donna vestita elegante, seduta con le gambe accavallate, un cappello a falda larga e le ciglia folte. Arrivarono i tre moschettieri che si sedutero accanto a lei. Alcune mosche iniziarono ad infastidire la donna. Il moschettiere americano intervenne per primo, offrendo il suo aiuto. Sguinò la spada e tagliò in due una mosca in orizzontale e la donna lo ringraziò impressionata. L'italiano divertito intervenne per secondo e con la sua spada divise un'altra mosca in verticale, dall'alto al basso. Anche in questo caso la donna si complementò stupita. Infine fu il turno del cinese che mosse la sua spada ma la mosca continuò a muoversi. Nessuno capì, ma poi guardando meglio si accorsero che la mosca si stava coprendo perché le aveva tagliato il pene.

### BCN_Raquel_3

**local-fw-turbo-int8**

> Una situació que passa a dintre d'una empresa, a la sala de reunions, el cap està assegut aquí i al davant hi ha un treballador sord i aquí hi ha un intèrpret. Llavors, el cap està molt enfadat i li diu Aquest treballador sord fa la feina fatal, la producció està baixant. Parla moltíssim. A mi no m'agrada això. Li prepararé els papers perquè no està bé. A veure, que m'explica el per què està fent això. La intèrpret interpreta tot tal qual i li explica al treballador sord Estàs treballant molt malament, no estan contents amb tu i et vol dir alguna cosa. Llavors, el cap continua Si us plau, digue-li que signi els papers de l'acomiadament oficialment. I la intèrpret diu Uau, ha de fer el rol d'intèrpret i li diu al treballador sord El cap diu que et farà fora I el sord diu Però per què? A mi em farà fora? A mi? Jo faig la meva feina malament? I diu Fill de puta! I la intèrpret es queda parada i el sord li diu Digue-li això, fill de puta! I la intèrpret li diu Vols que interpreti fill de puta? I la intèrpret diu al cap Fill de puta! I el cap enfadat li dona un cop de puny a la intèrpreta I ell acaba a terra I el sord es queda parat I diu No, no, que ho he dit jo! I el que és la intèrpret I el que és la intèrpret No, no, no, que ho he dit jo! I li ho he dit jo!

**groq-whisper-large-v3**

>  Una situació que passa a dintre d'una empresa, a la sala de reunions. El cap està assegut aquí i al davant hi ha un treballador sort i aquí hi ha una intèrpret. Llavors el cap està molt enfadat i li diu Aquest treballador sort fa la feina fatal. La producció està baixant. Parla moltíssim. A mi no m'agrada això Li prepararé els papers perquè no està bé A veure, que m'expliqui el per què està fent això La intèrpret interpreta tot tal qual i li explica al treballador que estàs treballant molt malament no estan contents amb tu i et vol dir alguna cosa Llavors el cap continua Si us plau, digue-li que signi els papers de l'acomiadament oficialment i la intèrpret diu uau, ha de fer el rol d'intèrpret i li diu al treballador sort el cap diu que et farà fora i el sort diu, però per què? A mi em farà fora? A mi? Jo faig la meva feina malament? i diu, fill de puta i la intèrpret es queda parada i el sort li diu digue-li això, fill de puta i la intèrpret li diu, vols que interpreti fill de puta? i la intèrpret diu al cap fill de puta i el cap enfadat li dona un cop de puny a la intèrpret i ell acaba a terra i aleshores queda parat i diu no, no, que ho he dit jo

**groq-whisper-large-v3-turbo**

>  Una situació que passa a dintre d'una empresa, a la sala de reunions El cap està assegut aquí i al davant hi ha un treballador sord I aquí hi ha un intèrpret Llavors el cap està molt enfadat i li diu Aquest treballador sord fa la feina fatal, la producció està baixant Parla moltíssim A mi no m'agrada això. Li prepararé els papers perquè no està bé. A veure, que m'explica el per què està fent això. La intèrpret interpreta tot tal qual i li explica al treballador que estàs treballant molt malament, no estan contents amb tu i et vol dir alguna cosa. Llavors el cap continua. Si us plau, digue-li que signi els papers de l'acomiadament oficialment. i la intèrpret diu, uau, ha de fer el rol d'intèrpret, i li diu al treballador sort, el cap diu que et farà fora, i el sort diu, però per què? A mi em farà fora? A mi? Jo faig la meva feina malament? I diu, fill de puta! I la intèrpret es queda parada, i el sort li diu, digue-li això, fill de puta! I la intèrpret li diu, vols que interpreti fill de puta? I la intèrpret diu al cap, fill de puta! I el cap enfadat li dona un cop de puny a la intèrpreta i ella acaba a terra. i aleshores queda parat i diu no, no, que ho he dit jo
