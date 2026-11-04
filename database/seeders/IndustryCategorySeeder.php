<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class IndustryCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalogues() as $businessType => $categories) {
            $this->seedTree($businessType, $categories);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     */
    private function seedTree(string $businessType, array $nodes, ?int $parentId = null): void
    {
        foreach ($nodes as $position => $node) {
            $category = Category::query()->updateOrCreate(
                [
                    'organization_id' => null,
                    'business_type' => $businessType,
                    'slug' => $node['slug'],
                ],
                [
                    'parent_id' => $parentId,
                    'name_az' => $node['az'],
                    'name_ru' => $node['ru'],
                    'name_en' => $node['en'],
                    'sort_order' => $position + 1,
                    'is_active' => true,
                ],
            );

            if (! empty($node['children'])) {
                $this->seedTree($businessType, $node['children'], $category->id);
            }
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function catalogues(): array
    {
        return [
            'electronics' => $this->electronics(),
            'pharmacy' => $this->pharmacy(),
            'food' => $this->food(),
            'retail' => $this->retail(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function electronics(): array
    {
        return [
            $this->node('electronics-mobile-phones', 'Mobil telefonlar', 'Мобильные телефоны', 'Mobile phones', [
                $this->node('electronics-apple-iphone', 'Apple iPhone modelləri', 'Модели Apple iPhone', 'Apple iPhone models'),
                $this->node('electronics-samsung-galaxy', 'Samsung Galaxy modelləri', 'Модели Samsung Galaxy', 'Samsung Galaxy models'),
                $this->node('electronics-xiaomi', 'Xiaomi modelləri', 'Модели Xiaomi', 'Xiaomi models'),
                $this->node('electronics-redmi', 'Redmi modelləri', 'Модели Redmi', 'Redmi models'),
                $this->node('electronics-poco', 'POCO modelləri', 'Модели POCO', 'POCO models'),
                $this->node('electronics-honor', 'HONOR modelləri', 'Модели HONOR', 'HONOR models'),
                $this->node('electronics-other-smartphones', 'Digər smartfonlar', 'Другие смартфоны', 'Other smartphones'),
                $this->node('electronics-feature-phones', 'Düyməli telefonlar', 'Кнопочные телефоны', 'Feature phones'),
            ]),
            $this->node('electronics-phone-accessories', 'Telefon aksesuarları', 'Аксессуары для телефонов', 'Phone accessories', [
                $this->node('electronics-phone-cables', 'Telefon kabelləri', 'Кабели для телефонов', 'Phone cables'),
                $this->node('electronics-adapters', 'Adapterlər', 'Адаптеры', 'Adapters'),
                $this->node('electronics-powerbanks', 'Powerbanklar', 'Внешние аккумуляторы', 'Power banks'),
                $this->node('electronics-phone-cases', 'Telefon qabları', 'Чехлы для телефонов', 'Phone cases'),
                $this->node('electronics-screen-protectors', 'Ekran qoruyucuları', 'Защитные стёкла и плёнки', 'Screen protectors'),
                $this->node('electronics-earphones', 'Qulaqlıqlar', 'Наушники', 'Earphones'),
                $this->node('electronics-bluetooth-earphones', 'Bluetooth qulaqlıqlar', 'Bluetooth-наушники', 'Bluetooth earphones'),
                $this->node('electronics-phone-holders', 'Telefon tutacaqları', 'Держатели для телефонов', 'Phone holders'),
                $this->node('electronics-memory-cards', 'Yaddaş kartları', 'Карты памяти', 'Memory cards'),
                $this->node('electronics-wireless-chargers', 'Simsiz şarj cihazları', 'Беспроводные зарядные устройства', 'Wireless chargers'),
            ]),
            $this->node('electronics-computers-accessories', 'Kompüterlər və aksesuarlar', 'Компьютеры и аксессуары', 'Computers and accessories', [
                $this->node('electronics-laptops', 'Noutbuklar', 'Ноутбуки', 'Laptops'),
                $this->node('electronics-desktops', 'Stolüstü kompüterlər', 'Настольные компьютеры', 'Desktop computers'),
                $this->node('electronics-monitors', 'Monitorlar', 'Мониторы', 'Monitors'),
                $this->node('electronics-keyboards', 'Klaviaturalar', 'Клавиатуры', 'Keyboards'),
                $this->node('electronics-mice', 'Kompüter siçanları', 'Компьютерные мыши', 'Computer mice'),
                $this->node('electronics-printers', 'Printerlər', 'Принтеры', 'Printers'),
                $this->node('electronics-scanners', 'Skanerlər', 'Сканеры', 'Scanners'),
                $this->node('electronics-ssd', 'SSD disklər', 'SSD-диски', 'SSD drives'),
                $this->node('electronics-hdd', 'HDD disklər', 'HDD-диски', 'HDD drives'),
                $this->node('electronics-usb-drives', 'USB fleş yaddaşlar', 'USB-флеш-накопители', 'USB flash drives'),
                $this->node('electronics-routers', 'Routerlər', 'Роутеры', 'Routers'),
                $this->node('electronics-computer-bags', 'Kompüter çantaları', 'Сумки для компьютеров', 'Computer bags'),
                $this->node('electronics-webcams', 'Veb-kameralar', 'Веб-камеры', 'Webcams'),
            ]),
            $this->node('electronics-tv-audio-video', 'Televizor və audio-video məhsulları', 'Телевизоры и аудио-видео', 'TV and audio-video', [
                $this->node('electronics-smart-tv', 'Smart televizorlar', 'Smart-телевизоры', 'Smart TVs'),
                $this->node('electronics-led-tv', 'LED televizorlar', 'LED-телевизоры', 'LED TVs'),
                $this->node('electronics-tv-remotes', 'Televizor pultları', 'Пульты для телевизоров', 'TV remotes'),
                $this->node('electronics-tv-box', 'TV Box cihazları', 'Устройства TV Box', 'TV boxes'),
                $this->node('electronics-hdmi-cables', 'HDMI kabellər', 'HDMI-кабели', 'HDMI cables'),
                $this->node('electronics-bluetooth-speakers', 'Bluetooth dinamiklər', 'Bluetooth-колонки', 'Bluetooth speakers'),
                $this->node('electronics-soundbars', 'Soundbarlar', 'Саундбары', 'Soundbars'),
                $this->node('electronics-projectors', 'Proyektorlar', 'Проекторы', 'Projectors'),
            ]),
            $this->node('electronics-large-appliances', 'Böyük məişət texnikası', 'Крупная бытовая техника', 'Large home appliances', [
                $this->node('electronics-refrigerators', 'Soyuducular', 'Холодильники', 'Refrigerators'),
                $this->node('electronics-washing-machines', 'Paltaryuyan maşınlar', 'Стиральные машины', 'Washing machines'),
                $this->node('electronics-dishwashers', 'Qabyuyan maşınlar', 'Посудомоечные машины', 'Dishwashers'),
                $this->node('electronics-air-conditioners', 'Kondisionerlər', 'Кондиционеры', 'Air conditioners'),
                $this->node('electronics-gas-stoves', 'Qaz plitələri', 'Газовые плиты', 'Gas stoves'),
                $this->node('electronics-electric-ovens', 'Elektrik sobaları', 'Электрические духовки', 'Electric ovens'),
                $this->node('electronics-microwave-ovens', 'Mikrodalğalı sobalar', 'Микроволновые печи', 'Microwave ovens'),
                $this->node('electronics-kitchen-hoods', 'Aspiratorlar', 'Вытяжки', 'Kitchen hoods'),
            ]),
            $this->node('electronics-small-appliances', 'Kiçik məişət texnikası', 'Мелкая бытовая техника', 'Small home appliances', [
                $this->node('electronics-kettles', 'Elektrik çaydanları', 'Электрические чайники', 'Electric kettles'),
                $this->node('electronics-blenders', 'Blenderlər', 'Блендеры', 'Blenders'),
                $this->node('electronics-mixers', 'Mikserlər', 'Миксеры', 'Mixers'),
                $this->node('electronics-toasters', 'Tosterlər', 'Тостеры', 'Toasters'),
                $this->node('electronics-coffee-machines', 'Qəhvə aparatları', 'Кофемашины', 'Coffee machines'),
                $this->node('electronics-vacuum-cleaners', 'Tozsoranlar', 'Пылесосы', 'Vacuum cleaners'),
                $this->node('electronics-irons', 'Ütülər', 'Утюги', 'Irons'),
                $this->node('electronics-hair-dryers', 'Fenlər', 'Фены', 'Hair dryers'),
                $this->node('electronics-hair-straighteners', 'Saç düzləşdiriciləri', 'Выпрямители для волос', 'Hair straighteners'),
                $this->node('electronics-electric-shavers', 'Elektrik təraş maşınları', 'Электробритвы', 'Electric shavers'),
                $this->node('electronics-food-processors', 'Mətbəx kombaynları', 'Кухонные комбайны', 'Food processors'),
            ]),
            $this->node('electronics-other', 'Digər elektronika', 'Другая электроника', 'Other electronics', [
                $this->node('electronics-smart-watches', 'Smart saatlar', 'Умные часы', 'Smart watches'),
                $this->node('electronics-tablets', 'Planşetlər', 'Планшеты', 'Tablets'),
                $this->node('electronics-game-consoles', 'Oyun konsolları', 'Игровые приставки', 'Game consoles'),
                $this->node('electronics-gaming-accessories', 'Oyun aksesuarları', 'Игровые аксессуары', 'Gaming accessories'),
                $this->node('electronics-security-cameras', 'Təhlükəsizlik kameraları', 'Камеры видеонаблюдения', 'Security cameras'),
                $this->node('electronics-smart-home', 'Ağıllı ev cihazları', 'Устройства умного дома', 'Smart home devices'),
                $this->node('electronics-batteries', 'Batareyalar', 'Батарейки и аккумуляторы', 'Batteries'),
                $this->node('electronics-extension-cords', 'Uzadıcılar', 'Удлинители', 'Extension cords'),
                $this->node('electronics-electrical-accessories', 'Elektrik aksesuarları', 'Электрические аксессуары', 'Electrical accessories'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pharmacy(): array
    {
        return [
            $this->node('pharmacy-pain-fever', 'Ağrıkəsici və qızdırmasalıcı vasitələr', 'Обезболивающие и жаропонижающие', 'Pain and fever relief', [
                $this->node('pharmacy-paracetamol', 'Parasetamol tərkibli preparatlar', 'Препараты с парацетамолом', 'Paracetamol medicines'),
                $this->node('pharmacy-ibuprofen', 'İbuprofen tərkibli preparatlar', 'Препараты с ибупрофеном', 'Ibuprofen medicines'),
                $this->node('pharmacy-other-painkillers', 'Digər ağrıkəsicilər', 'Другие обезболивающие', 'Other pain relievers'),
                $this->node('pharmacy-antipyretics', 'Qızdırmasalıcı preparatlar', 'Жаропонижающие препараты', 'Antipyretics'),
            ]),
            $this->node('pharmacy-cold-respiratory', 'Soyuqdəymə və tənəffüs sistemi məhsulları', 'Средства от простуды и для дыхательной системы', 'Cold and respiratory products', [
                $this->node('pharmacy-nasal-sprays', 'Burun spreyləri', 'Назальные спреи', 'Nasal sprays'),
                $this->node('pharmacy-throat-sprays', 'Boğaz spreyləri', 'Спреи для горла', 'Throat sprays'),
                $this->node('pharmacy-cough-syrups', 'Öskürək siropları', 'Сиропы от кашля', 'Cough syrups'),
                $this->node('pharmacy-throat-lozenges', 'Boğaz pastilləri', 'Пастилки для горла', 'Throat lozenges'),
                $this->node('pharmacy-saline-solutions', 'Duzlu burun məhlulları', 'Солевые растворы для носа', 'Saline nasal solutions'),
                $this->node('pharmacy-cold-remedies', 'Soyuqdəymə preparatları', 'Противопростудные препараты', 'Cold remedies'),
            ]),
            $this->node('pharmacy-digestive', 'Mədə-bağırsaq məhsulları', 'Средства для желудочно-кишечного тракта', 'Digestive health', [
                $this->node('pharmacy-antacids', 'Antasidlər', 'Антациды', 'Antacids'),
                $this->node('pharmacy-probiotics', 'Probiotiklər', 'Пробиотики', 'Probiotics'),
                $this->node('pharmacy-digestive-enzymes', 'Həzm fermentləri', 'Пищеварительные ферменты', 'Digestive enzymes'),
                $this->node('pharmacy-rehydration', 'Rehidratasiya məhlulları', 'Растворы для регидратации', 'Rehydration solutions'),
                $this->node('pharmacy-other-digestive', 'Digər həzm sistemi preparatları', 'Другие препараты для пищеварения', 'Other digestive medicines'),
            ]),
            $this->node('pharmacy-vitamins-minerals', 'Vitaminlər və minerallar', 'Витамины и минералы', 'Vitamins and minerals', [
                $this->node('pharmacy-vitamin-c', 'Vitamin C', 'Витамин C', 'Vitamin C'),
                $this->node('pharmacy-vitamin-d', 'Vitamin D', 'Витамин D', 'Vitamin D'),
                $this->node('pharmacy-vitamin-b', 'B qrupu vitaminləri', 'Витамины группы B', 'B vitamins'),
                $this->node('pharmacy-multivitamins', 'Multivitaminlər', 'Мультивитамины', 'Multivitamins'),
                $this->node('pharmacy-magnesium', 'Maqnezium', 'Магний', 'Magnesium'),
                $this->node('pharmacy-calcium', 'Kalsium', 'Кальций', 'Calcium'),
                $this->node('pharmacy-zinc', 'Sink', 'Цинк', 'Zinc'),
                $this->node('pharmacy-iron', 'Dəmir preparatları', 'Препараты железа', 'Iron supplements'),
                $this->node('pharmacy-childrens-vitamins', 'Uşaq vitaminləri', 'Детские витамины', "Children's vitamins"),
            ]),
            $this->node('pharmacy-medical-supplies', 'Tibbi ləvazimatlar', 'Медицинские принадлежности', 'Medical supplies', [
                $this->node('pharmacy-thermometers', 'Termometrlər', 'Термометры', 'Thermometers'),
                $this->node('pharmacy-blood-pressure-monitors', 'Təzyiq ölçən cihazlar', 'Тонометры', 'Blood pressure monitors'),
                $this->node('pharmacy-glucose-meters', 'Qlükometrlər', 'Глюкометры', 'Glucose meters'),
                $this->node('pharmacy-test-strips', 'Test zolaqları', 'Тест-полоски', 'Test strips'),
                $this->node('pharmacy-medical-masks', 'Tibbi maskalar', 'Медицинские маски', 'Medical masks'),
                $this->node('pharmacy-medical-gloves', 'Tibbi əlcəklər', 'Медицинские перчатки', 'Medical gloves'),
                $this->node('pharmacy-syringes', 'Şprislər', 'Шприцы', 'Syringes'),
                $this->node('pharmacy-bandages', 'Bintlər', 'Бинты', 'Bandages'),
                $this->node('pharmacy-sterile-gauze', 'Steril tənziflər', 'Стерильная марля', 'Sterile gauze'),
                $this->node('pharmacy-plasters', 'Plastırlar', 'Пластыри', 'Plasters'),
                $this->node('pharmacy-antiseptics', 'Antiseptik məhlullar', 'Антисептические растворы', 'Antiseptic solutions'),
                $this->node('pharmacy-first-aid-kits', 'İlk yardım dəstləri', 'Аптечки первой помощи', 'First aid kits'),
            ]),
            $this->node('pharmacy-mother-child', 'Ana və uşaq məhsulları', 'Товары для мам и детей', 'Mother and child', [
                $this->node('pharmacy-diapers', 'Uşaq bezləri', 'Подгузники', 'Diapers'),
                $this->node('pharmacy-baby-food', 'Uşaq qidaları', 'Детское питание', 'Baby food'),
                $this->node('pharmacy-baby-creams', 'Uşaq kremləri', 'Детские кремы', 'Baby creams'),
                $this->node('pharmacy-baby-shampoos', 'Uşaq şampunları', 'Детские шампуни', 'Baby shampoos'),
                $this->node('pharmacy-feeding-bottles', 'Süd butulkaları', 'Бутылочки для кормления', 'Feeding bottles'),
                $this->node('pharmacy-pacifiers', 'Əmziklər', 'Пустышки', 'Pacifiers'),
                $this->node('pharmacy-baby-hygiene', 'Uşaq gigiyena məhsulları', 'Детская гигиена', 'Baby hygiene'),
            ]),
            $this->node('pharmacy-hygiene-cosmetics', 'Şəxsi gigiyena və kosmetika', 'Личная гигиена и косметика', 'Personal care and cosmetics', [
                $this->node('pharmacy-toothpaste', 'Diş məcunları', 'Зубные пасты', 'Toothpaste'),
                $this->node('pharmacy-toothbrushes', 'Diş fırçaları', 'Зубные щётки', 'Toothbrushes'),
                $this->node('pharmacy-mouthwash', 'Ağız yaxalayıcıları', 'Ополаскиватели для полости рта', 'Mouthwash'),
                $this->node('pharmacy-shampoos', 'Şampunlar', 'Шампуни', 'Shampoos'),
                $this->node('pharmacy-soaps', 'Sabunlar', 'Мыло', 'Soaps'),
                $this->node('pharmacy-hand-creams', 'Əl kremləri', 'Кремы для рук', 'Hand creams'),
                $this->node('pharmacy-sunscreen', 'Günəşdən qoruyucu kremlər', 'Солнцезащитные кремы', 'Sunscreen'),
                $this->node('pharmacy-skincare', 'Dəriyə qulluq məhsulları', 'Средства ухода за кожей', 'Skin care'),
                $this->node('pharmacy-sanitary-products', 'Gigiyenik salfetlər', 'Гигиенические прокладки', 'Sanitary products'),
            ]),
            $this->node('pharmacy-other-medicines', 'Digər dərman kateqoriyaları', 'Другие категории лекарств', 'Other medicines', [
                $this->node('pharmacy-antiallergic', 'Allergiya əleyhinə preparatlar', 'Противоаллергические препараты', 'Anti-allergy medicines'),
                $this->node('pharmacy-dermatology', 'Dermatoloji preparatlar', 'Дерматологические препараты', 'Dermatology medicines'),
                $this->node('pharmacy-eye-ear', 'Göz və qulaq preparatları', 'Препараты для глаз и ушей', 'Eye and ear medicines'),
                $this->node('pharmacy-cardiovascular', 'Ürək-damar sistemi preparatları', 'Сердечно-сосудистые препараты', 'Cardiovascular medicines'),
                $this->node('pharmacy-diabetes', 'Diabet preparatları', 'Препараты для лечения диабета', 'Diabetes medicines'),
                $this->node('pharmacy-prescription', 'Reseptlə satılan dərmanlar', 'Рецептурные препараты', 'Prescription medicines'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function food(): array
    {
        return [
            $this->node('food-menu', 'Menyu', 'Меню', 'Menu', [
                $this->node('food-breakfast', 'Səhər yeməkləri', 'Завтраки', 'Breakfast'),
                $this->node('food-soups', 'Şorbalar', 'Супы', 'Soups'),
                $this->node('food-salads', 'Salatlar', 'Салаты', 'Salads'),
                $this->node('food-main-courses', 'Əsas yeməklər', 'Основные блюда', 'Main courses'),
                $this->node('food-pizza', 'Pizza', 'Пицца', 'Pizza'),
                $this->node('food-burgers', 'Burgerlər', 'Бургеры', 'Burgers'),
                $this->node('food-sandwiches', 'Sendviçlər', 'Сэндвичи', 'Sandwiches'),
                $this->node('food-pasta', 'Pasta və makaron', 'Паста и макароны', 'Pasta'),
                $this->node('food-desserts', 'Desertlər', 'Десерты', 'Desserts'),
                $this->node('food-bakery', 'Şirniyyat və un məmulatları', 'Выпечка', 'Bakery'),
            ]),
            $this->node('food-beverages', 'İçkilər', 'Напитки', 'Beverages', [
                $this->node('food-coffee', 'Qəhvə', 'Кофе', 'Coffee'),
                $this->node('food-tea', 'Çay', 'Чай', 'Tea'),
                $this->node('food-cold-drinks', 'Soyuq içkilər', 'Холодные напитки', 'Cold drinks'),
                $this->node('food-fresh-juices', 'Təzə şirələr', 'Свежевыжатые соки', 'Fresh juices'),
                $this->node('food-water', 'Su', 'Вода', 'Water'),
            ]),
            $this->node('food-ingredients', 'İnqrediyentlər', 'Ингредиенты', 'Ingredients', [
                $this->node('food-meat-poultry', 'Ət və quş əti', 'Мясо и птица', 'Meat and poultry'),
                $this->node('food-fish-seafood', 'Balıq və dəniz məhsulları', 'Рыба и морепродукты', 'Fish and seafood'),
                $this->node('food-dairy', 'Süd məhsulları', 'Молочные продукты', 'Dairy'),
                $this->node('food-vegetables', 'Tərəvəzlər', 'Овощи', 'Vegetables'),
                $this->node('food-fruits', 'Meyvələr', 'Фрукты', 'Fruits'),
                $this->node('food-grains-flour', 'Taxıl, un və makaron', 'Крупы, мука и макароны', 'Grains, flour and pasta'),
                $this->node('food-oils-sauces', 'Yağlar və souslar', 'Масла и соусы', 'Oils and sauces'),
                $this->node('food-spices', 'Ədviyyatlar', 'Специи', 'Spices'),
            ]),
            $this->node('food-operations', 'Restoran təchizatı', 'Ресторанные расходные материалы', 'Restaurant supplies', [
                $this->node('food-packaging', 'Qablaşdırma', 'Упаковка', 'Packaging'),
                $this->node('food-disposables', 'Birdəfəlik qablar', 'Одноразовая посуда', 'Disposable tableware'),
                $this->node('food-cleaning', 'Təmizlik vasitələri', 'Чистящие средства', 'Cleaning supplies'),
            ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function retail(): array
    {
        return [
            $this->node('retail-grocery', 'Ərzaq məhsulları', 'Продукты питания', 'Groceries'),
            $this->node('retail-beverages', 'İçkilər', 'Напитки', 'Beverages'),
            $this->node('retail-snacks', 'Qəlyanaltılar və şirniyyat', 'Снеки и сладости', 'Snacks and sweets'),
            $this->node('retail-bakery', 'Çörək və un məmulatları', 'Хлеб и выпечка', 'Bread and bakery'),
            $this->node('retail-frozen', 'Dondurulmuş məhsullar', 'Замороженные продукты', 'Frozen food'),
            $this->node('retail-household', 'Ev və məişət malları', 'Товары для дома', 'Household goods'),
            $this->node('retail-cleaning', 'Təmizlik vasitələri', 'Чистящие средства', 'Cleaning supplies'),
            $this->node('retail-personal-care', 'Şəxsi gigiyena', 'Личная гигиена', 'Personal care'),
            $this->node('retail-stationery', 'Dəftərxana ləvazimatları', 'Канцелярские товары', 'Stationery'),
            $this->node('retail-pet-supplies', 'Heyvan məhsulları', 'Товары для животных', 'Pet supplies'),
            $this->node('retail-seasonal', 'Mövsümi məhsullar', 'Сезонные товары', 'Seasonal goods'),
            $this->node('retail-other', 'Digər məhsullar', 'Другие товары', 'Other goods'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function node(string $slug, string $az, string $ru, string $en, array $children = []): array
    {
        return compact('slug', 'az', 'ru', 'en', 'children');
    }
}
