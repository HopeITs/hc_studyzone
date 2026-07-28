# Graph Report - .  (2026-07-27)

## Corpus Check
- Large corpus: 1511 files · ~265,757 words. Semantic extraction will be expensive (many Claude tokens). Consider running on a subfolder.

## Summary
- 5493 nodes · 11454 edges · 1444 communities (1426 shown, 18 thin omitted)
- Extraction: 90% EXTRACTED · 10% INFERRED · 0% AMBIGUOUS · INFERRED: 1167 edges (avg confidence: 0.67)
- Token cost: 243,957 input · 0 output

## Community Hubs (Navigation)
- Chart.js Rendering (vendor JS)
- Trix Rich Editor (vendor JS)
- Stats Overview Charts (vendor JS)
- Trix Rich Editor (vendor JS)
- Markdown Editor (vendor JS)
- Markdown Editor (vendor JS)
- Filament Forms (vendor JS)
- Trix Rich Editor (vendor JS)
- Chart.js Rendering (vendor JS)
- Chart.js Rendering (vendor JS)
- Chart.js Rendering (vendor JS)
- Filament Resource Registry
- Entry Services & Student Entry Flow
- FilePond File Upload (vendor JS)
- Chart.js Rendering (vendor JS)
- Stats Overview Charts (vendor JS)
- Markdown Editor (vendor JS)
- Markdown Editor (vendor JS)
- Trix Rich Editor (vendor JS)
- Chart.js Rendering (vendor JS)
- Trix Rich Editor (vendor JS)
- Filament Support Runtime (vendor JS)
- Stats Overview Charts (vendor JS)
- Reports & Bilingual Locale Switching
- Student Model & Education Stats
- Geography Models & Web Controllers
- Dashboard Chart Widgets
- Chart.js Rendering (vendor JS)
- Chart.js Rendering (vendor JS)
- Notifications (vendor JS)
- Stats Overview Charts (vendor JS)
- Filament Support Runtime (vendor JS)
- Stats Overview Charts (vendor JS)
- Entry & Student Resource Core
- Trix Rich Editor (vendor JS)
- Stats Overview Charts (vendor JS)
- Excel Export Collections
- Resource List Pages
- Resource Edit Pages
- Chart.js Rendering (vendor JS)
- Stats Overview Charts (vendor JS)
- Stats Overview Charts (vendor JS)
- Center Reporting Widgets
- Trix Rich Editor (vendor JS)
- Filament Support Runtime (vendor JS)
- Stats Overview Charts (vendor JS)
- Chart.js Rendering (vendor JS)
- Trix Rich Editor (vendor JS)
- Filament Support Runtime (vendor JS)
- Offline/Online Data Sync
- Stats Overview Charts (vendor JS)
- Access Control & Activity Log
- Stats Widgets & User Model
- Chart.js Rendering (vendor JS)
- Resource Create Pages
- Chart.js Rendering (vendor JS)
- Stats Overview Charts (vendor JS)
- Frontend Build Dependencies
- Education & Entry Enums
- Filament Echo / Websockets (vendor JS)
- Filament Support Runtime (vendor JS)
- Chart.js Rendering (vendor JS)
- Database Model Factories
- Trix Rich Editor (vendor JS)
- Chart.js Rendering (vendor JS)
- Trix Rich Editor (vendor JS)
- Stats Overview Charts (vendor JS)
- Trix Rich Editor (vendor JS)
- Chart.js Rendering (vendor JS)
- Chart.js Rendering (vendor JS)
- Public Robots Crawler
- Stats Overview Charts (vendor JS)
- Color Picker (vendor JS)
- Filament Resources
- Composer Require Filament
- Filament Echo / Websockets (vendor JS)
- Filament Echo / Websockets (vendor JS)
- Trix Rich Editor (vendor JS)
- Filament Support Runtime (vendor JS)
- Stats Overview Charts (vendor JS)
- Filament Exports
- Filament Resources
- Filament Resources
- Composer Scripts Post
- Filament Echo / Websockets (vendor JS)
- Filament Resources
- Filament Resources
- Composer Autoload Dev
- Filament Resources
- Public Images Logo
- Public Images New
- Composer Require Dev
- Public Images 419
- Filament Echo / Websockets (vendor JS)
- Composer Allow Plugins
- Public Images Sz
- Filament Echo / Websockets (vendor JS)
- Console Kernel
- Helpers Synchelperold
- Tests Feature Exampletest
- Public Images Loader
- Filament Forms (vendor JS)
- Filament Forms (vendor JS)
- Filament Forms (vendor JS)
- Filament Forms (vendor JS)
- Filament Forms (vendor JS)
- Filament Support Runtime (vendor JS)
- Composer Psr 4
- Db Migrations 2025
- Filament Forms (vendor JS)
- Ref Php Artisan
- Tests Unit Exampletest
- View Filament
- Composer Extra
- Composer Keywords
- Constants Tableconstants
- View Filament
- View Filament

## God Nodes (most connected - your core abstractions)
1. `_update()` - 88 edges
2. `_update()` - 84 edges
3. `x()` - 82 edges
4. `te()` - 76 edges
5. `V()` - 61 edges
6. `e` - 54 edges
7. `vd()` - 51 edges
8. `T()` - 48 edges
9. `Student` - 47 edges
10. `draw()` - 47 edges

## Surprising Connections (you probably didn't know these)
- `Permissive Robots Crawler Policy` --semantically_similar_to--> `Default Seeded Admin Credentials`  [INFERRED] [semantically similar]
  public/robots.txt → README.md
- `Permissive Robots Crawler Policy` --conceptually_related_to--> `Study Zone Project`  [INFERRED]
  public/robots.txt → README.md
- `getExtension()` --indirect_call--> `Ht()`  [INFERRED]
  public/js/filament/forms/components/file-upload.js → public/js/filament/forms/components/markdown-editor.js
- `_getTestState()` --indirect_call--> `Ht()`  [INFERRED]
  public/js/filament/forms/components/file-upload.js → public/js/filament/forms/components/markdown-editor.js
- `getServicesForCategoryAndCenter()` --calls--> `Service`  [INFERRED]
  app/Traits/HasCenterServicCategories.php → app/Models/Service.php

## Import Cycles
- None detected.

## Hyperedges (group relationships)
- **TALL Stack Technology Selection** — readme_laravel_framework, readme_filament_admin_panel, readme_tailwind_css, readme_alpine_js [INFERRED 0.85]
- **Ordered Project Bootstrap Sequence** — readme_gitlab_ssh_repository, readme_environment_configuration, readme_application_key_generation, readme_migrations_and_seeders, readme_frontend_asset_build, readme_local_development_server [EXTRACTED 1.00]

## Communities (1444 total, 18 thin omitted)

### Community 0 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.01
Nodes (102): Ac(), addControllers(), addPlugins(), addScales(), ae(), Af(), Cc(), Ce() (+94 more)

### Community 1 - "Trix Rich Editor (vendor JS)"
Cohesion: 0.02
Nodes (117): activateAttributeIfSupported(), appendStringToTextAtIndex(), applyBlockAttribute(), attachmentDidChangeAttributes(), attachmentDidChangeUploadProgress(), attachmentIsManaged(), attributeChangedCallback(), box() (+109 more)

### Community 2 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.02
Nodes (113): didClickDialogButton(), getSignificantNodesForIndex(), aa(), active(), addControllers(), addPlugins(), addScales(), an() (+105 more)

### Community 3 - "Trix Rich Editor (vendor JS)"
Cohesion: 0.03
Nodes (122): addAttribute(), addAttributeAtRange(), addAttributesAtRange(), addHTMLAttribute(), appendText(), applyBlockAttributeAtRange(), canBeGroupedWith(), charAt() (+114 more)

### Community 4 - "Markdown Editor (vendor JS)"
Cohesion: 0.04
Nodes (112): _a(), Aa(), ad(), af(), al(), An(), Be(), bo() (+104 more)

### Community 5 - "Markdown Editor (vendor JS)"
Cohesion: 0.05
Nodes (110): e(), i(), l(), Ni(), o(), t(), u(), be() (+102 more)

### Community 6 - "Filament Forms (vendor JS)"
Cohesion: 0.08
Nodes (59): [g](), [x](), Sg(), $c(), D(), E(), g(), H() (+51 more)

### Community 7 - "Trix Rich Editor (vendor JS)"
Cohesion: 0.04
Nodes (91): ArrowLeft(), ArrowRight(), attachmentManagerDidRequestRemovalOfAttachment(), backspace(), breakFormattedBlock(), breaksOnReturn(), canDecreaseBlockAttributeLevel(), canSetCurrentAttribute() (+83 more)

### Community 8 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.05
Nodes (91): adjustHitBoxes(), an(), ba(), beforeDatasetDraw(), beforeDatasetsDraw(), beforeDraw(), bl(), Bn() (+83 more)

### Community 9 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.04
Nodes (88): addBox(), addElements(), afterBuildTicks(), afterCalculateLabelRotation(), afterDataLimits(), afterDraw(), afterFit(), afterSetDimensions() (+80 more)

### Community 10 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.04
Nodes (79): abutsStart(), after(), before(), contains(), count(), Dc(), defaultZone(), dg() (+71 more)

### Community 11 - "Filament Resource Registry"
Cohesion: 0.10
Nodes (21): default(), label(), labels(), self, PricesRelationManager, EducationsRelationManager, Education, PivotRow (+13 more)

### Community 12 - "Entry Services & Student Entry Flow"
Cohesion: 0.05
Nodes (20): AddEntryServices, ServicesRelationManager, EntryStats, CreateStudentEntry, ViewStudentEntry, ServiceHelper, StudentEntryController, EntryService (+12 more)

### Community 13 - "FilePond File Upload (vendor JS)"
Cohesion: 0.05
Nodes (58): ba(), bi(), c(), ca(), clickPercent(), constructor(), de(), e() (+50 more)

### Community 14 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.05
Nodes (74): afterTickToLabelConversion(), ah(), applyStack(), aspectRatio(), au(), bd(), beforeTickToLabelConversion(), _calculateBarIndexPixels() (+66 more)

### Community 15 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.05
Nodes (67): Ao(), applyStack(), ar(), as(), _calculateBarIndexPixels(), _calculateBarValuePixels(), calculateCircumference(), calculateLabelRotation() (+59 more)

### Community 16 - "Markdown Editor (vendor JS)"
Cohesion: 0.04
Nodes (15): Pr(), Bi(), bn(), Id(), ji(), kd(), on(), qd() (+7 more)

### Community 17 - "Markdown Editor (vendor JS)"
Cohesion: 0.15
Nodes (62): at(), B(), br(), Bt(), ca(), cd(), Cr(), Ct() (+54 more)

### Community 18 - "Trix Rich Editor (vendor JS)"
Cohesion: 0.05
Nodes (62): attachFiles(), beforeinput(), canApplyToDocument(), ce(), compositionDidLoadSnapshot(), compositionend(), compositionstart(), compositionupdate() (+54 more)

### Community 19 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.05
Nodes (58): active(), add(), _animateOptions(), As(), B(), bh(), bi(), _cachedScopes() (+50 more)

### Community 20 - "Trix Rich Editor (vendor JS)"
Cohesion: 0.07
Nodes (53): add(), applyKeyboardCommand(), attachmentEditorDidRequestRemovalOfAttachment(), canBeGrouped(), Ci(), clearSelection(), connectedCallback(), constructor() (+45 more)

### Community 21 - "Filament Support Runtime (vendor JS)"
Cohesion: 0.07
Nodes (44): ai(), apply(), co(), ds(), $e(), ei(), es(), Et() (+36 more)

### Community 22 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.07
Nodes (54): acquireContext(), adjustHitBoxes(), afterDraw(), bc(), Bl(), clear(), _computeLabelArea(), _computeTitleHeight() (+46 more)

### Community 23 - "Reports & Bilingual Locale Switching"
Cohesion: 0.06
Nodes (15): Action, LanguageSwitcher, Reports, CityResource, TopEducationsStudentsEntriesTable, TopEducationsTable, TopLevels, TopLevelsEntriesTable (+7 more)

### Community 24 - "Student Model & Education Stats"
Cohesion: 0.06
Nodes (15): default(), self, Attribute, Student, StudentEducation, StudentObserver, AppServiceProvider, StudentFactory (+7 more)

### Community 25 - "Geography Models & Web Controllers"
Cohesion: 0.05
Nodes (16): CenterResource, Controller, SocialLinkController, City, CategorySeeder, CenterSeeder, CitySeeder, DatabaseSeeder (+8 more)

### Community 26 - "Dashboard Chart Widgets"
Cohesion: 0.07
Nodes (11): StudentCountOverTime, ActiveCenterEntriesChart, AllEntriesChart, EntryGenderChart, EntryToolsChart, FrequentStudentsChart, HourlyAverageChart, NewStudentsChart (+3 more)

### Community 27 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.06
Nodes (49): _a(), Ao(), bf(), clone(), co(), create(), Ds(), dtFormatter() (+41 more)

### Community 28 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.05
Nodes (48): ad(), ar(), average(), Br(), Ch(), dataset(), Do(), ee() (+40 more)

### Community 29 - "Notifications (vendor JS)"
Cohesion: 0.06
Nodes (23): actions(), button(), constructor(), danger(), dispatch(), dispatchSelf(), dispatchTo(), duration() (+15 more)

### Community 30 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.07
Nodes (43): afterBuildTicks(), afterCalculateLabelRotation(), afterDataLimits(), afterFit(), afterSetDimensions(), afterTickToLabelConversion(), afterUpdate(), beforeBuildTicks() (+35 more)

### Community 31 - "Filament Support Runtime (vendor JS)"
Cohesion: 0.10
Nodes (41): xt(), Ae(), at(), B(), Cn(), de(), dt(), En() (+33 more)

### Community 32 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.07
Nodes (40): addEventListener(), Bi(), bindEvents(), bindResponsiveEvents(), bindUserEvents(), _checkEventBindings(), cs(), Ct() (+32 more)

### Community 33 - "Entry & Student Resource Core"
Cohesion: 0.05
Nodes (4): EntryResource, EntriesRelationManager, StudentResource, Step

### Community 34 - "Trix Rich Editor (vendor JS)"
Cohesion: 0.07
Nodes (39): attachmentForFile(), attributesForFile(), cacheViewForObject(), createAttachmentNodes(), createChildView(), createContainerElement(), createDocumentFragmentForSync(), createElement() (+31 more)

### Community 35 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.08
Nodes (39): addElements(), afterDatasetsUpdate(), buildOrUpdateControllers(), buildOrUpdateElements(), configure(), _dataCheck(), datasetScopeKeys(), _destroy() (+31 more)

### Community 36 - "Excel Export Collections"
Cohesion: 0.10
Nodes (11): CustomEntriesExport, CustomStudentsExport, TopEducationsByEntriesExport, TopEducationsByStudentsExport, TopLevelsByEntriesExport, TopLevelsExport, Illuminate\Contracts\Support\Responsable, Illuminate\Support\Collection (+3 more)

### Community 37 - "Resource List Pages"
Cohesion: 0.07
Nodes (15): ListCenters, ListCities, ListEducation, ListEntries, ListServiceCategories, ListServices, ListSocialLinks, ListStreets (+7 more)

### Community 38 - "Resource Edit Pages"
Cohesion: 0.07
Nodes (12): EditCenter, EditCity, EditEducation, EditEntry, Model, EditServiceCategory, EditService, EditSocialLink (+4 more)

### Community 39 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.06
Nodes (37): al(), bt(), cd(), constructor(), _d(), data(), dl(), getDevicePixelRatio() (+29 more)

### Community 40 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.09
Nodes (37): C(), Co(), _computeLabelSizes(), cr(), Et(), format(), formats(), getLabelAndValue() (+29 more)

### Community 41 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.07
Nodes (36): ac(), Ai(), alpha(), be(), ca(), ea(), en(), fe() (+28 more)

### Community 42 - "Center Reporting Widgets"
Cohesion: 0.08
Nodes (14): ActiveDaysPerCenterExport, EntriesByCenterExport, Dashboard, ActiveDaysByCenter, EntriesByCenterTable, Center, Attribute, BelongsTo (+6 more)

### Community 43 - "Trix Rich Editor (vendor JS)"
Cohesion: 0.07
Nodes (35): actionIsExternal(), canBeConsolidatedWith(), canInvokeAction(), compositionControllerDidBlur(), compositionControllerDidRender(), compositionControllerDidSyncDocumentView(), compositionDidAddAttachment(), compositionDidChangeAttachmentPreviewURL() (+27 more)

### Community 44 - "Filament Support Runtime (vendor JS)"
Cohesion: 0.15
Nodes (34): _a(), aa(), ba(), Be(), br(), Ca(), ce(), Dn() (+26 more)

### Community 45 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.09
Nodes (34): chartOptionScopes(), describe(), _a(), add(), ba(), _cachedScopes(), createResolver(), datasetElementScopeKeys() (+26 more)

### Community 46 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.09
Nodes (33): afterDatasetsUpdate(), At(), gd(), generateLabels(), getDatasetMeta(), getDataVisibility(), _getLegendItemAt(), getMaxBorderWidth() (+25 more)

### Community 47 - "Trix Rich Editor (vendor JS)"
Cohesion: 0.07
Nodes (32): canAcceptDataTransfer(), compositionControllerDidFocus(), compositionDidRequestChangingSelectionToLocationRange(), createDOMRangeFromLocationRange(), createDOMRangeFromPoint(), createLocationRangeFromDOMRange(), didMouseDown(), domRangeWithinElement() (+24 more)

### Community 48 - "Filament Support Runtime (vendor JS)"
Cohesion: 0.24
Nodes (31): Bi(), d(), di(), f(), fn(), g(), I(), ir() (+23 more)

### Community 49 - "Offline/Online Data Sync"
Cohesion: 0.10
Nodes (8): SyncData, SyncDataOld, SyncLocalToOnline, SyncOnlineToLocal, SyncData, SyncHelper, Filament\Pages\Page, Illuminate\Console\Command

### Community 50 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.09
Nodes (30): At(), average(), beforeDraw(), dataset(), Fa(), getCenterPoint(), getMaximumSize(), getProps() (+22 more)

### Community 51 - "Access Control & Activity Log"
Cohesion: 0.10
Nodes (15): CustomActivitylogResource, ListActivitylogs, ViewActivitylog, AdminAccessMiddleware, EnsureUserIsActive, SetLocale, AdminPanelProvider, Closure (+7 more)

### Community 52 - "Stats Widgets & User Model"
Cohesion: 0.08
Nodes (16): StudentEducationStats, StudentEntryTypeStats, StudentStats, UserStats, MultiToolUsage, StatsOverviewWidget, LogOptions, User (+8 more)

### Community 53 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.09
Nodes (29): aa(), alpha(), du(), Gc(), greyscale(), hslString(), _i(), ih() (+21 more)

### Community 54 - "Resource Create Pages"
Cohesion: 0.10
Nodes (11): CreateCenter, CreateCity, CreateEducation, CreateEntry, CreateServiceCategory, CreateService, CreateSocialLink, CreateStreet (+3 more)

### Community 55 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.09
Nodes (28): acquireContext(), addEventListener(), bindResponsiveEvents(), bu(), explainFromTokens(), gu(), Ht(), isAttached() (+20 more)

### Community 56 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.12
Nodes (27): afterAutoSkip(), buildLookupTable(), buildTicks(), computeTickLimit(), diff(), _drawArgs(), Fi(), _generate() (+19 more)

### Community 57 - "Frontend Build Dependencies"
Cohesion: 0.08
Nodes (25): autoprefixer, axios, concurrently, @heroicons/react, html5-qrcode, laravel-vite-plugin, dependencies, @heroicons/react (+17 more)

### Community 58 - "Education & Entry Enums"
Cohesion: 0.10
Nodes (12): default(), self, default(), self, default(), self, default(), self (+4 more)

### Community 59 - "Filament Echo / Websockets (vendor JS)"
Cohesion: 0.11
Nodes (9): ar(), b(), cr(), g(), Me(), P(), qt(), rr() (+1 more)

### Community 60 - "Filament Support Runtime (vendor JS)"
Cohesion: 0.17
Nodes (20): _activate(), _componentStrategy(), data(), _download(), _generateRequirements(), _getModule(), h(), _handleAlias() (+12 more)

### Community 61 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.11
Nodes (23): afterAutoSkip(), buildLookupTable(), fa(), Fn(), getAllParsedValues(), getDataTimestamps(), getDecimalForPixel(), getDecimalForValue() (+15 more)

### Community 62 - "Database Model Factories"
Cohesion: 0.13
Nodes (8): CenterFactory, EducationFactory, static, StudentEducationFactory, StudentEntryFactory, static, UserFactory, Illuminate\Database\Eloquent\Factories\Factory

### Community 63 - "Trix Rich Editor (vendor JS)"
Cohesion: 0.10
Nodes (22): canSetCurrentTextAttribute(), compositionControllerDidRequestDeselectingAttachment(), compositionDidStartEditingAttachment(), didClickAttachment(), dragstart(), findAttachmentForElement(), getAttachmentAndPositionById(), getAttachmentById() (+14 more)

### Community 64 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.12
Nodes (22): em(), eras(), extract(), fastNumbers(), im(), isEnglish(), It(), Jm() (+14 more)

### Community 65 - "Trix Rich Editor (vendor JS)"
Cohesion: 0.18
Nodes (20): appendAttachmentWithAttributes(), appendBlockForAttributesWithElement(), appendBlockForElement(), appendBlockForTextNode(), appendEmptyBlock(), appendPiece(), appendStringWithAttributes(), find() (+12 more)

### Community 66 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.11
Nodes (20): Ft(), Rs(), chartOptionScopes(), constructor(), data(), describe(), fl(), Fs() (+12 more)

### Community 67 - "Trix Rich Editor (vendor JS)"
Cohesion: 0.16
Nodes (19): canDecreaseNestingLevel(), canIncreaseNestingLevel(), decreaseNestingLevel(), deleteByDrag(), formatIndent(), formatOutdent(), formatRemove(), getCurrentAttributes() (+11 more)

### Community 68 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.12
Nodes (19): $c(), color(), darken(), desaturate(), Dh(), ei(), hexString(), lighten() (+11 more)

### Community 69 - "Chart.js Rendering (vendor JS)"
Cohesion: 0.13
Nodes (18): Dd(), fl(), Hd(), Hf(), id(), interpolate(), Na(), od() (+10 more)

### Community 70 - "Public Robots Crawler"
Cohesion: 0.14
Nodes (18): Permissive Robots Crawler Policy, Alpine.js, Application Key Generation, Default Seeded Admin Credentials, Environment Configuration (.env), Filament Admin Panel, Frontend Asset Build Pipeline, GitLab SSH Repository (hopecenter/hope_studyzone) (+10 more)

### Community 71 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.20
Nodes (16): buildOrUpdateScales(), cl(), D(), E(), ensureScalesHaveIDs(), Eo(), Fo(), getScale() (+8 more)

### Community 74 - "Composer Require Filament"
Cohesion: 0.15
Nodes (13): require, archtechx/enums, blade-ui-kit/blade-icons, eightynine/filament-advanced-widgets, filament/filament, filament/spatie-laravel-translatable-plugin, laravel/framework, laravel/tinker (+5 more)

### Community 75 - "Filament Echo / Websockets (vendor JS)"
Cohesion: 0.23
Nodes (8): C(), H(), I(), J(), O(), U(), V(), L()

### Community 76 - "Filament Echo / Websockets (vendor JS)"
Cohesion: 0.18
Nodes (12): Ce(), De(), di(), e(), Ht(), Ie(), Re(), t() (+4 more)

### Community 77 - "Trix Rich Editor (vendor JS)"
Cohesion: 0.18
Nodes (13): didMutate(), findSignificantMutations(), getEndData(), getMutationsByType(), getMutationSummary(), getTextChangesFromCharacterData(), getTextChangesFromChildList(), getTextMutationSummary() (+5 more)

### Community 78 - "Filament Support Runtime (vendor JS)"
Cohesion: 0.26
Nodes (13): da(), Ii(), mr(), na(), pe(), Pn(), Qr(), ta() (+5 more)

### Community 79 - "Stats Overview Charts (vendor JS)"
Cohesion: 0.21
Nodes (13): uu(), al(), cancel(), _createDescriptors(), _descriptors(), getPlugin(), _notify(), _notifyStateChanges() (+5 more)

### Community 80 - "Filament Exports"
Cohesion: 0.18
Nodes (4): EntryExporter, StudentExporter, Filament\Actions\Exports\Exporter, Filament\Actions\Exports\Models\Export

### Community 83 - "Composer Scripts Post"
Cohesion: 0.17
Nodes (12): scripts, dev, post-autoload-dump, post-root-package-install, post-update-cmd, Composer\\Config::disableProcessTimeout, Illuminate\\Foundation\\ComposerScripts::postAutoloadDump, npx concurrently -c \"#93c5fd,#c4b5fd,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"npm run dev\" --names='server,queue,vite (+4 more)

### Community 84 - "Filament Echo / Websockets (vendor JS)"
Cohesion: 0.18
Nodes (12): Be(), ei(), ii(), le(), ni(), oi(), r(), ri() (+4 more)

### Community 85 - "Filament Resources"
Cohesion: 0.22
Nodes (4): ViewEntry, ViewService, ViewStudent, Filament\Resources\Pages\ViewRecord

### Community 87 - "Composer Autoload Dev"
Cohesion: 0.18
Nodes (10): autoload-dev, psr-4, description, license, minimum-stability, name, prefer-stable, Tests\\ (+2 more)

### Community 89 - "Public Images Logo"
Cohesion: 0.38
Nodes (10): Study Zone Logo (public/images/logo.png), Study Zone Brand Identity, Brand Color Palette (deep navy #123B54 and amber orange #F79E1B), Education / Study Platform Product Domain, Flat Vector Two-Tone Logo Style (transparent background, scalable mark), Graduation Cap Motif (academic achievement), Lightbulb Motif (ideas, insight, learning), Magnifying Glass Motif (search, study, discovery) (+2 more)

### Community 90 - "Public Images New"
Cohesion: 0.33
Nodes (10): New Logo (public/images/new_logo.png), Hope StudyZone Brand Identity, Education / Study Platform Product, Graduation Cap / Mortarboard Mark, Possible White / Low-Contrast Logo Elements, Learning & Insight Visual Metaphor, Spiral Lightbulb Mark, Orange Monochrome Brand Palette (+2 more)

### Community 91 - "Composer Require Dev"
Cohesion: 0.25
Nodes (8): require-dev, fakerphp/faker, laravel/pail, laravel/pint, laravel/sail, mockery/mockery, nunomaduro/collision, phpunit/phpunit

### Community 92 - "Public Images 419"
Cohesion: 0.36
Nodes (8): HTTP 419 Page Expired Error, Error Page Static Asset, Flat Vector Character Illustration Style, Two People Playing Video Games on a Couch (Scene Motif), Idle Session / CSRF Token Expiry Metaphor, 419 Error Page Illustration (419-error-img.png), Orange/Amber and Slate Blue Brand Palette, public/images Static Asset Directory

### Community 93 - "Filament Echo / Websockets (vendor JS)"
Cohesion: 0.29
Nodes (8): Dt(), Fe(), He(), i(), ir(), Mt(), nr(), rt()

### Community 94 - "Composer Allow Plugins"
Cohesion: 0.29
Nodes (7): pestphp/pest-plugin, php-http/discovery, config, allow-plugins, optimize-autoloader, preferred-install, sort-packages

### Community 95 - "Public Images Sz"
Cohesion: 0.43
Nodes (7): sz.png (StudyZone Wordmark Logo), Educational Study Platform Product, Orange Accent Color (Highlighted S and Z Initials), StudyZone Brand Identity, Transparent PNG Brand Asset for Header and Navbar, White Letterforms for Dark Backgrounds, Wordmark Logo (Bold Sans-Serif Lettering)

### Community 96 - "Filament Echo / Websockets (vendor JS)"
Cohesion: 0.29
Nodes (7): a(), at(), d(), f(), H(), ji(), pt()

### Community 97 - "Console Kernel"
Cohesion: 0.40
Nodes (3): Kernel, Illuminate\Console\Scheduling\Schedule, Illuminate\Foundation\Console\Kernel

### Community 99 - "Tests Feature Exampletest"
Cohesion: 0.40
Nodes (3): Illuminate\Foundation\Testing\TestCase, ExampleTest, TestCase

### Community 100 - "Public Images Loader"
Cohesion: 0.47
Nodes (6): Loader GIF (public/images/loader.gif), Animated GIF Multi-Frame Format, Async Wait-State Feedback Pattern, Dark / Black Backdrop Overlay Styling, Loading Indicator / Spinner Animation, Public Static Image Asset (public/images)

### Community 106 - "Filament Support Runtime (vendor JS)"
Cohesion: 0.33
Nodes (6): q(), ar(), ft(), kn(), Sr(), Ye()

### Community 107 - "Composer Psr 4"
Cohesion: 0.40
Nodes (5): autoload, psr-4, App\\, Database\\Factories\\, Database\\Seeders\\

### Community 110 - "Ref Php Artisan"
Cohesion: 0.50
Nodes (4): post-create-project-cmd, @php artisan key:generate --ansi, @php artisan migrate --graceful --ansi, @php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\

### Community 112 - "View Filament"
Cohesion: 0.50
Nodes (3): syncData, syncLocalToOnline, syncOnlineToLocal

### Community 113 - "Composer Extra"
Cohesion: 0.67
Nodes (3): extra, laravel, dont-discover

### Community 114 - "Composer Keywords"
Cohesion: 0.67
Nodes (3): keywords, framework, laravel

## Ambiguous Edges - Review These
- `Study Zone Project` → `Laravel Sanctum / Breeze / Jetstream Authentication`  [AMBIGUOUS]
  README.md · relation: references
- `Study Zone Project` → `Laravel Excel`  [AMBIGUOUS]
  README.md · relation: references
- `Study Zone Project` → `Spatie Media Library`  [AMBIGUOUS]
  README.md · relation: references
- `Two People Playing Video Games on a Couch (Scene Motif)` → `Idle Session / CSRF Token Expiry Metaphor`  [AMBIGUOUS]
  public/images/419-error-img.png · relation: conceptually_related_to
- `Loader GIF (public/images/loader.gif)` → `Dark / Black Backdrop Overlay Styling`  [AMBIGUOUS]
  public/images/loader.gif · relation: references
- `Study Zone Brand Identity` → `Public Web Asset (served from public/images, used in headers, favicons, emails)`  [AMBIGUOUS]
  public/images/logo.png · relation: conceptually_related_to
- `New Logo (public/images/new_logo.png)` → `Possible White / Low-Contrast Logo Elements`  [AMBIGUOUS]
  public/images/new_logo.png · relation: references
- `Hope StudyZone Brand Identity` → `Possible White / Low-Contrast Logo Elements`  [AMBIGUOUS]
  public/images/new_logo.png · relation: conceptually_related_to
- `StudyZone Brand Identity` → `Wordmark Logo (Bold Sans-Serif Lettering)`  [AMBIGUOUS]
  public/images/sz.png · relation: conceptually_related_to

## Knowledge Gaps
- **76 isolated node(s):** `TableConstants`, `$schema`, `name`, `type`, `description` (+71 more)
  These have ≤1 connection - possible missing edges or undocumented components.
- **18 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **What is the exact relationship between `Study Zone Project` and `Laravel Sanctum / Breeze / Jetstream Authentication`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._
- **What is the exact relationship between `Study Zone Project` and `Laravel Excel`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._
- **What is the exact relationship between `Study Zone Project` and `Spatie Media Library`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._
- **What is the exact relationship between `Two People Playing Video Games on a Couch (Scene Motif)` and `Idle Session / CSRF Token Expiry Metaphor`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **What is the exact relationship between `Loader GIF (public/images/loader.gif)` and `Dark / Black Backdrop Overlay Styling`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._
- **What is the exact relationship between `Study Zone Brand Identity` and `Public Web Asset (served from public/images, used in headers, favicons, emails)`?**
  _Edge tagged AMBIGUOUS (relation: conceptually_related_to) - confidence is low._
- **What is the exact relationship between `New Logo (public/images/new_logo.png)` and `Possible White / Low-Contrast Logo Elements`?**
  _Edge tagged AMBIGUOUS (relation: references) - confidence is low._