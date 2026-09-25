"""
A Tudástár cikkeinek forrása (setup/posts/*.html + setup/posts/posts.json).

A mentésben lévő három "Lorem ipsum dolor" bejegyzés helyére kerülnek (azonos ID-vel),
a telepítő `blog_posts` lépése írja be őket. A cikkek tartalma blokk-markup
(core/heading, core/paragraph, core/list), a dev/canon.sh-n is átfut.
"""
import json
import os
import sys

sys.path.insert(0, os.path.dirname(__file__))
from blocks import h, p, ul, ol, HOME  # noqa

OUT = os.path.join(os.path.dirname(__file__), '../../wp-content/themes/vasskisgep/setup/posts/')

TOPICS = {
    'gepvalasztas': 'Gépválasztás',
    'karbantartas': 'Karbantartás',
    'szezonalis-teendok': 'Szezonális teendők',
}

posts = [
    {
        'id': 1,
        'slug': 'hogyan-valasszunk-funyirot',
        'title': 'Hogyan válasszunk fűnyírót?',
        'topic': 'gepvalasztas',
        'image': 'stiga-lawnmower-in-garden',
        'excerpt': 'A kert mérete, a terep és a fű jellege dönti el, hogy benzines, akkumulátoros, önjáró vagy robotfűnyíró a jó választás. Összegyűjtöttük a legfontosabb szempontokat.',
        'content': [
            p('Jó fűnyírót nem a legerősebb motor vagy a legtöbb funkció alapján érdemes választani, hanem a kerthez és a használati szokásokhoz igazítva. Az alábbi szempontok segítenek szűkíteni a kört – a végső döntésben üzleteinkben személyesen is segítünk.'),
            h('1. Mekkora a nyírandó terület?', 3),
            ul('<strong>300 m² alatt</strong> jellemzően elég egy 32–38 cm vágásszélességű elektromos vagy akkumulátoros gép.',
               '<strong>300–800 m²</strong> között a 40–46 cm vágásszélesség az ideális; itt az akkumulátoros és a benzinmotoros gépek is jól teljesítenek.',
               '<strong>800 m² felett</strong> érdemes önjáró, 46–53 cm-es benzinmotoros gépben vagy fűnyíró traktorban, illetve robotfűnyíróban gondolkodni.'),
            h('2. Milyen a terep?', 3),
            p('Emelkedős, lejtős kertben sokat számít az <strong>önjáró (kerékhajtású) kivitel</strong>: a gép maga halad, Önnek csak vezetnie kell. Egyenetlen talajon a nagyobb hátsó kerék és a könnyen gördülő futómű teszi kényelmesebbé a munkát.'),
            h('3. Benzines, akkumulátoros vagy robot?', 3),
            ul('<strong>Benzinmotoros:</strong> nagy teljesítmény, korlátlan üzemidő, magas vagy nedves fűben is bírja. Rendszeres karbantartást (olaj, gyertya, levegőszűrő) igényel.',
               '<strong>Akkumulátoros:</strong> csendes, könnyű, azonnal indul, alig kell karbantartani. Egy rendszeren belül az akkumulátor más gépekben (fűkasza, sövényvágó, lombfúvó) is használható.',
               '<strong>Robotfűnyíró:</strong> rendszeres, apró nyírással sűrű, ápolt gyepet ad, szinte felügyelet nélkül. Telepítést (határoló vezeték vagy navigáció) és a kert előkészítését igényli.'),
            h('4. Gyűjtés, mulcsozás vagy oldalkidobás?', 3),
            p('A <strong>fűgyűjtős</strong> gép rendezett, tiszta gyepet hagy. A <strong>mulcsozás</strong> során a gép apróra vágja és visszajuttatja a füvet, ami természetes tápanyag – gyakori nyírásnál ideális. Magas, elhanyagolt füvön az <strong>oldalkidobás</strong> a leggyorsabb. Sok gép mindhárom módot tudja.'),
            h('5. Kényelmi szempontok', 3),
            ul('központi vágásmagasság-állítás egy karral,',
               'telítettségjelzős fűgyűjtő kosár,',
               'állítható magasságú, összecsukható tolókar a könnyebb tároláshoz,',
               'mosócsatlakozó a vágóház tisztításához.'),
            h('Segítünk dönteni', 3),
            p('Kisvárdai és vásárosnaményi üzletünkben a gépeket megnézheti, kézbe veheti, és munkatársaink a kertje alapján ajánlanak megfelelő típust. A megvásárolt gépet összeszerelve, kipróbálva adjuk át, és a szervizt is vállaljuk. <a href="' + HOME + '/termekkategoria/kerteszeti-gepek/funyirok/">Nézze meg fűnyíróinkat</a>, vagy <a href="' + HOME + '/ajanlatkeres/">kérjen ajánlatot</a>.'),
        ],
    },
    {
        'id': 215,
        'slug': 'funyiro-teli-tarolasa',
        'title': 'A fűnyíró felkészítése a téli tárolásra',
        'topic': 'szezonalis-teendok',
        'image': 'kerti-kisgepek-karbantartasa',
        'excerpt': 'Néhány egyszerű lépéssel elkerülhető, hogy tavasszal ne induljon a gép. Üzemanyag, tisztítás, kés, akkumulátor – mire figyeljen a szezon végén.',
        'content': [
            p('A szezon utolsó nyírása után érdemes fél órát szánni a fűnyíróra. A téli hónapokban a benzin elöregszik, a lerakódott fűmaradék nedvességet tart, az akkumulátor pedig lemerülhet – ezek a leggyakoribb okai annak, hogy tavasszal nem indul a gép.'),
            h('Benzinmotoros fűnyíró', 3),
            ol('<strong>Üzemanyag:</strong> járassa a motort, amíg a tankban lévő benzin elfogy, vagy töltse fel üzemanyag-stabilizátorral kezelt friss benzinnel. A hónapokig álló benzin gyantásodik, és eltömítheti a karburátort.',
               '<strong>Gyújtásbiztonság:</strong> bármilyen munka előtt húzza le a gyújtógyertya-pipát.',
               '<strong>Tisztítás:</strong> a vágóházból kaparja ki a rászáradt füvet (ha van mosócsatlakozó, használja). A gépet ne fektesse a karburátoros oldalára, és ne mossa nagynyomású vízzel.',
               '<strong>Olajcsere:</strong> a használt olaj savas anyagokat tartalmaz, ezért a szezon végén érdemes lecserélni. A típust és a mennyiséget a kezelési útmutató adja meg.',
               '<strong>Levegőszűrő és gyertya:</strong> tisztítsa vagy cserélje a levegőszűrőt, ellenőrizze a gyújtógyertyát.',
               '<strong>Kés:</strong> a tompa kés tépi a füvet, ami barnuló fűvéget és betegségeket okoz. A téli szünet jó alkalom az élezésre vagy cserére.'),
            h('Akkumulátoros fűnyíró', 3),
            ul('Az akkumulátort vegye ki a gépből, és kb. 40–60%-os töltöttséggel tárolja.',
               'Fagymentes, száraz helyen tartsa (ideálisan 5–25 °C között), ne a fagyos garázsban vagy fészerben.',
               'A tél közepén ellenőrizze a töltöttséget, ha szükséges, töltse vissza.'),
            h('Tárolás', 3),
            p('A gépet száraz, szellőző helyen tárolja, letakarva a portól. Az összecsukható tolókar helyet takarít meg, de figyeljen, hogy a bowdenek ne törjenek meg.'),
            h('Szezon előtti átvizsgálás', 3),
            p('Ha nem szeretne bajlódni vele, hozza be a gépet a téli hónapokban: szakszervizünk elvégzi a karbantartást, az élezést és az átvizsgálást, így tavasszal az első napon indulhat a munka. <a href="' + HOME + '/szerviz/">Szervizbejelentés</a>'),
        ],
    },
    {
        'id': 217,
        'slug': 'lancfuresz-karbantartasa',
        'title': 'Láncfűrész karbantartása: lánc, vezetőlemez, levegőszűrő',
        'topic': 'karbantartas',
        'image': 'stihl-tools-in-forest',
        'excerpt': 'Az éles lánc, a jól kenő vezetőlemez és a tiszta levegőszűrő a biztonságos, hatékony vágás alapja. A legfontosabb rendszeres teendők egy helyen.',
        'content': [
            p('A láncfűrész teljesítménye és biztonsága nagyrészt a rendszeres karbantartáson múlik. Egy tompa lánc nemcsak lassabban vág, hanem nagyobb erőkifejtést igényel, és növeli a visszarúgás veszélyét.'),
            h('Minden használat előtt', 3),
            ul('Ellenőrizze a <strong>láncfeszességet</strong>: hideg állapotban a lánc feszesen illeszkedjen a vezetőlemez alsó éléhez, de kézzel (kesztyűben) könnyen húzható legyen.',
               'Nézze meg, van-e elegendő <strong>lánckenő olaj</strong>, és működik-e a kenés: járó motornál világos felület felé tartva olajpermet látszik.',
               'Próbálja ki a <strong>láncféket</strong> és a gázkar-reteszt.'),
            h('A lánc élezése', 3),
            p('A lánc akkor tompa, ha vágás helyett porszerű forgácsot ad, vagy nyomni kell a gépet. Élezéshez a lánc osztásához illő kerek reszelő és reszelővezető kell; minden fogat azonos szögben és azonos számú húzással élezzen. A mélységhatárolókat időnként laposreszelővel kell visszavenni. Ha bizonytalan, élezze meg szervizünk – gépi élezéssel egyenletes lesz a lánc.'),
            h('Vezetőlemez', 3),
            ul('Tisztítsa ki a hornyot és az olajfuratokat a lerakódott forgácstól.',
               'Láncélezéskor fordítsa meg a vezetőlemezt, így egyenletesen kopik.',
               'A sorja a lemez élén laposreszelővel eltávolítható; erősen kopott, lépcsős lemezt cserélni kell.'),
            h('Levegőszűrő és gyújtógyertya', 3),
            p('Poros munkában a levegőszűrő gyorsan eltömődik, ami teljesítményvesztést és nagyobb fogyasztást okoz. Rendszeresen tisztítsa a gyártói útmutató szerint, és ellenőrizze a gyújtógyertyát is.'),
            h('Biztonság', 3),
            p('Láncfűrészt mindig vágásbiztos nadrágban, védőcsizmában, sisakban arc- és hallásvédővel, valamint kesztyűben használjon. Munkaruházatot és védőfelszerelést üzleteinkben is talál.'),
            p('<a href="' + HOME + '/termekkategoria/erdeszeti-gepek/lancfureszek/">Láncfűrészek</a> · <a href="' + HOME + '/termekkategoria/alkatreszek-tartozekok/lancok-vezetolemezek/">Láncok, vezetőlemezek</a> · <a href="' + HOME + '/szerviz/">Szerviz</a>'),
        ],
    },
]

if __name__ == '__main__':
    os.makedirs(OUT, exist_ok=True)
    meta = []
    for post in posts:
        open(os.path.join(OUT, post['slug'] + '.html'), 'w').write('\n\n'.join(post['content']) + '\n')
        meta.append({k: post[k] for k in ('id', 'slug', 'title', 'topic', 'image', 'excerpt')})
    json.dump({'topics': TOPICS, 'posts': meta}, open(os.path.join(OUT, 'posts.json'), 'w'), ensure_ascii=False, indent=4)
    print('ok', len(posts))
