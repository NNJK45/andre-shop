import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Link, Route, Routes, useLocation, useNavigate, useParams } from 'react-router-dom';

const TOKEN_KEY = 'andre_shop_token';
const money = (value) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XAF', maximumFractionDigits: 0 }).format(Number(value || 0));
const unwrap = (payload) => payload?.data ?? payload ?? [];
async function request(path, { token, ...options } = {}) {
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    let body = options.body;
    if (body && typeof body === 'object' && !(body instanceof FormData)) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(body); }
    if (token) headers.Authorization = `Bearer ${token}`;
    const response = await fetch(`/api${path}`, { ...options, headers, body });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const errors = payload.errors ? Object.values(payload.errors).flat().join(' ') : '';
        throw new Error(errors || payload.message || 'Une erreur est survenue.');
    }
    return payload;
}
const ShopContext = createContext(null);
const useShop = () => useContext(ShopContext);

function ShopProvider({ children }) {
    const [products, setProducts] = useState([]);
    const [categories, setCategories] = useState([]);
    const [loading, setLoading] = useState(true);
    const [catalogError, setCatalogError] = useState('');
    const [token, setToken] = useState(() => localStorage.getItem(TOKEN_KEY));
    const [user, setUser] = useState(null);
    const [cart, setCart] = useState(null);
    const [toast, setToast] = useState(null);
    const [authOpen, setAuthOpen] = useState(false);
    const [cartOpen, setCartOpen] = useState(false);
    const notify = (message, tone = 'success') => { setToast({ message, tone }); window.setTimeout(() => setToast(null), 3500); };
    const loadCatalog = async () => {
        setLoading(true); setCatalogError('');
        try {
            const [cats, items] = await Promise.all([request('/customer/catalog/categories'), request('/customer/catalog/products')]);
            setCategories(unwrap(cats)); setProducts(unwrap(items));
        } catch (error) { setCatalogError(error.message); } finally { setLoading(false); }
    };
    const refreshCart = async () => {
        if (!token) { setCart(null); return; }
        try { setCart(unwrap(await request('/customer/cart', { token }))); } catch { setCart(null); }
    };
    useEffect(() => { loadCatalog(); }, []);
    useEffect(() => { refreshCart(); }, [token]);
    useEffect(() => { if (token) { request('/auth/me', { token }).then((result) => setUser(unwrap(result))).catch(() => { localStorage.removeItem(TOKEN_KEY); setToken(null); }); } }, [token]);
    const login = async (values) => {
        const result = await request('/auth/login', { method: 'POST', body: { ...values, device_name: 'andre-shop-web' } });
        localStorage.setItem(TOKEN_KEY, result.token); setToken(result.token); setUser(result.user); setAuthOpen(false); notify('Bienvenue chez André Shop.');
    };
    const register = async (values) => {
        const result = await request('/auth/register', { method: 'POST', body: { ...values, device_name: 'andre-shop-web' } });
        localStorage.setItem(TOKEN_KEY, result.token); setToken(result.token); setUser(result.user); setAuthOpen(false); notify('Votre compte est prêt.');
    };
    const logout = async () => { try { await request('/auth/logout', { method: 'POST', token }); } catch {} localStorage.removeItem(TOKEN_KEY); setToken(null); setUser(null); setCart(null); notify('Vous êtes déconnecté.'); };
    const addToCart = async (product) => {
        if (!token) { setAuthOpen(true); notify('Connectez-vous pour ajouter un article.', 'info'); return; }
        try { await request('/customer/cart/items', { method: 'POST', token, body: { product_id: product.id, quantity: 1 } }); await refreshCart(); setCartOpen(true); notify('Article ajouté au panier.'); }
        catch (error) { notify(error.message, 'error'); }
    };
    const updateCartItem = async (id, quantity) => {
        try { if (quantity < 1) await request(`/customer/cart/items/${id}`, { method: 'DELETE', token }); else await request(`/customer/cart/items/${id}`, { method: 'PATCH', token, body: { quantity } }); await refreshCart(); }
        catch (error) { notify(error.message, 'error'); }
    };
    const value = useMemo(() => ({ products, categories, loading, catalogError, token, user, cart, money, loadCatalog, refreshCart, login, register, logout, addToCart, updateCartItem, notify, authOpen, setAuthOpen, cartOpen, setCartOpen }), [products, categories, loading, catalogError, token, user, cart, toast, authOpen, cartOpen]);
    return <ShopContext.Provider value={value}>{children}{toast && <Toast {...toast} />}</ShopContext.Provider>;
}
function Toast({ message, tone }) { return <div className={`toast toast-${tone}`} role="status"><b>{tone === 'error' ? '!' : '✓'}</b>{message}</div>; }

function AppShell() {
    const location = useLocation();
    const admin = location.pathname.startsWith('/admin');
    useEffect(() => { const target = location.hash?.slice(1); if (!target) return; const timer = window.setTimeout(() => document.getElementById(target)?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 80); return () => window.clearTimeout(timer); }, [location.pathname, location.hash]);
    const { cartOpen, setCartOpen, authOpen, setAuthOpen } = useShop();
    if (admin) return <><Routes><Route path="/admin/*" element={<AdminPage />} /></Routes>{authOpen && <AuthModal onClose={() => setAuthOpen(false)} />}</>;
    return <><div className="announcement"><span>Livraison partout au Cameroun</span><span>•</span><span>Paiement sécurisé par mobile money</span><Link to="/contact" className="announcement-link">Besoin d'aide ?</Link></div><Header /><main><Routes><Route path="/" element={<HomePage />} /><Route path="/catalogue" element={<CatalogPage />} /><Route path="/produit/:slug" element={<ProductPage />} /><Route path="/checkout" element={<CheckoutPage />} /><Route path="/compte" element={<AccountPage />} /><Route path="/contact" element={<ContactPage />} /><Route path="/admin/*" element={<AdminPage />} /><Route path="*" element={<HomePage />} /></Routes></main><Footer />{cartOpen && <CartDrawer onClose={() => setCartOpen(false)} />}{authOpen && <AuthModal onClose={() => setAuthOpen(false)} />}<button className="floating-cart" onClick={() => setCartOpen(true)}>Panier <CartCount /></button></>;
}
function Header() {
    const { user, logout, setAuthOpen, categories } = useShop();
    const [search, setSearch] = useState('');
    const [menuOpen, setMenuOpen] = useState(false);
    const navigate = useNavigate();
    const submit = (e) => { e.preventDefault(); navigate('/catalogue' + (search ? '?q=' + encodeURIComponent(search) : '')); setMenuOpen(false); };
    const popular = categories.slice(0, 7);
    const categoryImages = { 'bureau-scolaire': '/images/categories/bureau-scolaire.jpg', 'electronique': '/images/categories/electronique.jpg', 'equipement-professionnel': '/images/categories/equipement-professionnel.jpg', 'hygiene-entretien': '/images/categories/hygiene-entretien.jpg', 'maison-cuisine': '/images/categories/maison-cuisine.jpg', 'mobilier': '/images/categories/mobilier.jpg' };
    const fallbackImages = Object.values(categoryImages);
    return <header className="site-header professional-header"><div className="header-main"><Link to="/" className="brand"><span className="brand-mark">A</span><span>andré<span className="brand-shop">shop</span></span></Link><div className="header-location"><span className="location-icon">⌖</span><div><small>Livrer à</small><strong>Douala, Cameroun</strong></div></div><form className="header-search" onSubmit={submit}><span>⌕</span><input aria-label="Rechercher" placeholder="Que recherchez-vous aujourd'hui ?" value={search} onChange={(e) => setSearch(e.target.value)} /><button type="submit">Rechercher</button></form><div className="header-actions">{user ? <Link to="/compte" className="header-account"><span className="account-icon">♙</span><span><small>Bonjour</small><strong>{user.name?.split(' ')[0] || 'vous'}</strong></span></Link> : <button className="header-account" onClick={() => setAuthOpen(true)}><span className="account-icon">♙</span><span><small>Mon espace</small><strong>Se connecter</strong></span></button>}{user && <button className="header-logout" onClick={logout} aria-label="Se déconnecter">↪</button>}<Link to="/checkout" className="header-cart"><span className="cart-icon">♧</span><span><small>Votre panier</small><strong>Panier <b><CartCount /></b></strong></span></Link></div></div><div className="header-nav-wrap"><div className="header-nav-inner"><button className={'category-menu-trigger ' + (menuOpen ? 'active' : '')} onClick={() => setMenuOpen(!menuOpen)}><span>☰</span> Toutes les catégories <i>⌄</i></button><nav className="main-nav"><Link to="/catalogue">Nos produits</Link><a href="/#offers">Promotions</a><a href="/#services">Nos services</a><a href="/#engagement">Pourquoi André Shop ?</a><Link to="/contact">Nous contacter</Link><Link to="/compte">Mon espace</Link></nav><div className="nav-highlight">✓ Paiement sécurisé par Nokash</div></div>{menuOpen && <div className="mega-menu"><div className="mega-menu-inner"><div className="mega-intro"><p className="eyebrow">EXPLORER LA BOUTIQUE</p><h3>Tout pour équiper<br /><em>vos projets.</em></h3><Link to="/catalogue" onClick={() => setMenuOpen(false)} className="text-link">Voir le catalogue <span>↗</span></Link></div><div className="mega-categories">{popular.map((category, index) => { const image = category.image_url || categoryImages[category.slug] || fallbackImages[index % fallbackImages.length]; return <Link key={category.id || category.slug} to={'/catalogue?category=' + (category.slug || category.id)} onClick={() => setMenuOpen(false)}><span className="mega-image"><img src={image} alt="" loading="lazy" /></span><span className={'mega-icon mega-icon-' + ((index % 4) + 1)}>{['⌂','◈','▣','✦'][index % 4]}</span><span><strong>{category.name}</strong><small>Découvrir la sélection</small></span><b>↗</b></Link>; })}</div></div></div>}</div></header>;
}
function CartCount() { const { cart } = useShop(); return <>{cart?.items?.reduce((sum, item) => sum + Number(item.quantity || 0), 0) || 0}</>; }
function HomePage() { return <><Hero /><CategoryShowcase /><TrustBar /><ServicesSection /><CatalogSection /><FeaturedBanner /></>; }
function Hero() {
    const { products } = useShop();
    const latest = products.slice(0, 10);
    const [activeIndex, setActiveIndex] = useState(0);
    const count = latest.length;
    useEffect(() => { if (activeIndex >= count && count) setActiveIndex(0); }, [count, activeIndex]);
    useEffect(() => { if (count < 2) return undefined; const timer = window.setInterval(() => setActiveIndex((index) => (index + 1) % count), 4500); return () => window.clearInterval(timer); }, [count]);
    const featured = latest[activeIndex] || products[0];
    const image = featured?.images?.find((item) => item.is_primary)?.url || featured?.images?.[0]?.url || featured?.image_url;
    const previous = () => setActiveIndex((index) => count ? (index - 1 + count) % count : 0);
    const next = () => setActiveIndex((index) => count ? (index + 1) % count : 0);
    return <section className="hero marketplace-hero"><div className="hero-copy"><p className="eyebrow">ANDRÉ SHOP · ÉQUIPEMENT AU CAMEROUN</p><h1>Équipez vos espaces.<br /><em>Vivez mieux.</em></h1><p className="hero-lead">Le meilleur de l'équipement maison, professionnel et scolaire, sélectionné pour le quotidien camerounais.</p><div className="hero-actions"><Link to="/catalogue" className="button button-orange">Découvrir nos produits <span>↗</span></Link><a className="text-link" href="/#engagement">Notre engagement <span>↓</span></a></div><div className="hero-proof"><span>✓ Produits sélectionnés</span><span>✓ Livraison partout au Cameroun</span></div></div><div className="hero-visual hero-carousel"><Link to={featured ? '/produit/' + (featured.slug || featured.id) : '/catalogue'} className="hero-carousel-link">{image ? <img key={featured?.id} src={image} alt={featured?.name || 'Équipement André Shop'} /> : <div className="hero-visual-fallback"><span>ANDRÉ<br />SHOP</span><b>ÉQUIPEMENT<br /><em>qui dure.</em></b></div>}<div className="hero-visual-shade"></div><div className="hero-visual-label"><small>NOUVEAUTÉS · {count ? String(activeIndex + 1).padStart(2, '0') + ' / ' + String(count).padStart(2, '0') : '—'}</small><strong>{featured?.name || 'Des essentiels pour tous vos projets'}</strong><span>{featured ? money(featured.price) : 'Qualité · Conseil · Proximité'}</span></div><span className="hero-badge">NOUVEAU</span></Link>{count > 1 && <div className="hero-carousel-controls"><button onClick={(event) => { event.preventDefault(); event.stopPropagation(); previous(); }} aria-label="Produit précédent">←</button><div className="hero-carousel-dots">{latest.map((product, index) => <button key={product.id} className={index === activeIndex ? 'active' : ''} onClick={(event) => { event.preventDefault(); event.stopPropagation(); setActiveIndex(index); }} aria-label={'Voir le produit ' + (index + 1)}></button>)}</div><button onClick={(event) => { event.preventDefault(); event.stopPropagation(); next(); }} aria-label="Produit suivant">→</button></div>}</div></section>;
}
function CategoryShowcase() {
    const { categories } = useShop();
    const categoryImages = {
        'bureau-scolaire': '/images/categories/bureau-scolaire.jpg',
        'electronique': '/images/categories/electronique.jpg',
        'equipement-professionnel': '/images/categories/equipement-professionnel.jpg',
        'hygiene-entretien': '/images/categories/hygiene-entretien.jpg',
        'maison-cuisine': '/images/categories/maison-cuisine.jpg',
        'mobilier': '/images/categories/mobilier.jpg'
    };
    const fallbackImages = Object.values(categoryImages);
    return <section className="category-showcase"><div className="category-showcase-head"><div><p className="eyebrow">TROUVER VOTRE UNIVERS</p><h2>Quel espace souhaitez-vous <em>équiper ?</em></h2></div><Link to="/catalogue" className="text-link">Toutes les catégories <span>↗</span></Link></div><div className="category-cards">{categories.slice(0, 6).map((category, index) => { const image = category.image_url || categoryImages[category.slug] || fallbackImages[index % fallbackImages.length]; return <Link key={category.id || category.slug} to={'/catalogue?category=' + (category.slug || category.id)} className={'category-card category-tone-' + ((index % 4) + 1)}><span className="category-card-image"><img src={image} alt={category.name} loading="lazy" /></span><span className="category-card-content"><span className="category-card-icon">{['⌂','◈','▣','✦','◌','◇'][index % 6]}</span><strong>{category.name}</strong><small>Voir les produits <span>↗</span></small></span></Link>; })}</div></section>;
}
function TrustBar() { return <section className="trust-bar" id="engagement">{[['✦','Qualité vérifiée','Des marques et références choisies'],['⌁','Livraison partout','Douala, Yaoundé et tout le Cameroun'],['◉','Paiement sécurisé','Mobile Money avec Nokash'],['♡','Service de proximité','Une équipe à votre écoute']].map(([icon,title,copy]) => <div key={title}><span className="trust-icon">{icon}</span><strong>{title}</strong><small>{copy}</small></div>)}</section>; }
function ServicesSection() {
    const services = [['⌁', 'Livraison rapide', 'Recevez vos équipements partout au Cameroun, avec un suivi clair.'], ['◈', 'Installation & mise en service', 'Un accompagnement pratique pour démarrer dans les meilleures conditions.'], ['◇', 'Devis professionnels', 'Une réponse adaptée aux restaurants, hôtels, écoles et entreprises.'], ['♡', 'Service après-vente', 'Notre équipe reste disponible après votre achat.']];
    return <section className="services-section" id="services"><div className="services-intro"><p className="eyebrow">NOS SERVICES</p><h2>Un accompagnement à la <em>hauteur.</em></h2><p>Chez André Shop, l'expérience ne s'arrête pas au paiement. Nous vous aidons à choisir, recevoir et utiliser vos équipements.</p></div><div className="services-grid">{services.map(([icon, title, copy]) => <article key={title} className="service-card"><span className="service-card-icon">{icon}</span><h3>{title}</h3><p>{copy}</p><a href="/#service">En savoir plus <span>↗</span></a></article>)}</div></section>;
}
function CatalogSection() {
    const { products, categories } = useShop();
    const [category, setCategory] = useState('');
    const visible = products.filter((product) => !category || product.category?.slug === category || String(product.category_id) === String(category)).slice(0, 8);
    return <section className="catalog-section marketplace-catalog" id="catalogue"><div className="section-heading"><div><p className="eyebrow">LA SÉLECTION ANDRÉ SHOP</p><h2>Les essentiels du <em>moment.</em></h2><p className="section-subtitle">Des produits utiles, fiables et disponibles maintenant.</p></div><Link to="/catalogue" className="text-link">Voir tout le catalogue <span>↗</span></Link></div><div className="catalog-toolbar"><div className="category-tabs"><button className={!category ? 'active' : ''} onClick={() => setCategory('')}>Tout voir</button>{categories.slice(0, 5).map((item) => <button key={item.id || item.slug} className={category === (item.slug || item.id) ? 'active' : ''} onClick={() => setCategory(item.slug || item.id)}>{item.name}</button>)}</div><span className="catalog-count">{visible.length} articles</span></div><ProductRail products={visible} /><div className="section-footer"><Link to="/catalogue" className="button button-light">Explorer tous les produits <span>↗</span></Link></div></section>;
}
function ProductRail({ products }) {
    if (!products.length) return <div className="empty-state"><strong>Le catalogue arrive bientôt.</strong><p>Nos équipes préparent une sélection pour vous.</p></div>;
    return <div className="product-rail">{products.map((product, index) => <ProductCard key={product.id} product={product} index={index} />)}</div>;
}
function FeaturedBanner() { return <section className="featured-banner" id="offers"><div><p className="eyebrow">POUR LES PROFESSIONNELS</p><h2>Des solutions pour<br /><em>faire grandir vos projets.</em></h2><p>Restaurants, hôtels, écoles, entreprises : nous vous accompagnons avec des équipements fiables et adaptés.</p><Link to="/catalogue" className="button button-orange">Parler à un conseiller <span>↗</span></Link></div><div className="featured-banner-art"><span>PRO</span><strong>ANDRÉ<br />SHOP</strong><small>MAISON · BUREAU · MÉTIER</small></div></section>;
}
function CatalogPage() {
    const params = new URLSearchParams(useLocation().search); const { categories } = useShop(); const [query, setQuery] = useState(params.get('q') || ''); const [category, setCategory] = useState(params.get('category') || '');
    return <section className="catalog-page"><div className="page-heading"><p className="eyebrow">LA BOUTIQUE</p><h1>Tous nos essentiels</h1><p>Des pièces choisies pour leur qualité, leur utilité et ce petit supplément d’âme.</p></div><CatalogToolbar categories={categories} category={category} setCategory={setCategory} query={query} setQuery={setQuery} large /><ProductGrid category={category} query={query} /></section>;
}
function CatalogToolbar({ categories, category, setCategory, query, setQuery, large = false }) { return <div className={`catalog-toolbar ${large ? 'catalog-toolbar-large' : ''}`}><div className="category-tabs"><button className={!category ? 'active' : ''} onClick={() => setCategory('')}>Tout voir</button>{categories.slice(0, large ? 20 : 5).map((item) => <button key={item.id || item.slug} className={category === (item.slug || item.id) ? 'active' : ''} onClick={() => setCategory(item.slug || item.id)}>{item.name}</button>)}</div><label className="mini-search">⌕<input placeholder={large ? 'Rechercher' : 'Filtrer'} value={query} onChange={(e) => setQuery(e.target.value)} /></label></div>; }
function ProductGrid({ category, query }) {
    const { products, loading, catalogError } = useShop(); const filtered = products.filter((product) => { const text = `${product.name || ''} ${product.category?.name || ''}`.toLowerCase(); return (!query || text.includes(query.toLowerCase())) && (!category || product.category?.slug === category || String(product.category_id) === String(category) || product.category?.id === category); });
    if (loading) return <div className="loading-grid">{[1,2,3,4].map((item) => <div className="skeleton-card" key={item}></div>)}</div>;
    if (catalogError) return <div className="empty-state"><strong>Le catalogue est momentanément indisponible.</strong><p>{catalogError}</p></div>;
    if (!filtered.length) return <div className="empty-state"><strong>Aucun article ne correspond à votre recherche.</strong><p>Essayez une autre catégorie ou un mot-clé.</p></div>;
    return <div className="product-grid">{filtered.map((product, index) => <ProductCard key={product.id} product={product} index={index} />)}</div>;
}
function ProductCard({ product, index }) {
    const { addToCart } = useShop(); const image = product.images?.find((item) => item.is_primary)?.url || product.images?.[0]?.url || product.image_url;
    return <article className="product-card"><Link to={`/produit/${product.slug || product.id}`} className={`product-visual tone-${(index % 4) + 1}`}>{image ? <img src={image} alt={product.name} /> : <ProductPlaceholder index={index} />}<span className="product-badge">{product.stock > 0 || product.is_available !== false ? 'DISPONIBLE' : 'ÉPUISÉ'}</span><span className="product-arrow">↗</span></Link><div className="product-info"><div><h3>{product.name}</h3><span className="product-category">{product.category?.name || 'Sélection André'}</span></div><div className="product-buy"><strong>{money(product.price)}</strong><button className="product-action" onClick={() => addToCart(product)} aria-label={`Ajouter ${product.name}`}>+</button></div></div></article>;
}
function ProductPlaceholder({ index = 0 }) { return <div className="placeholder-art"><span>{['OBJET','SOIN','MAISON','ESSENTIEL'][index % 4]}</span><b>ANDRÉ<br />SHOP</b><i></i></div>; }
function ProductPage() {
    const { slug } = useParams(); const { products, addToCart } = useShop(); const product = products.find((item) => String(item.slug || item.id) === String(slug));
    if (!product) return <div className="page-state"><h1>Produit introuvable</h1><Link className="button button-dark" to="/catalogue">Retour au catalogue</Link></div>;
    return <section className="product-page"><Link className="back-link" to="/catalogue">← Retour au catalogue</Link><div className="product-detail"><div className="detail-visual tone-2">{product.images?.[0]?.url ? <img src={product.images[0].url} alt={product.name} /> : <ProductPlaceholder index={1} />}</div><div className="detail-copy"><p className="eyebrow">{product.category?.name || 'SÉLECTION ANDRÉ'}</p><h1>{product.name}</h1><p className="detail-price">{money(product.price)}</p><p className="detail-description">{product.description || 'Une référence choisie avec soin pour sa qualité et sa simplicité d’usage.'}</p><div className="detail-meta"><span>✓ En stock</span><span>↗ Livraison au Cameroun</span></div><button className="button button-dark button-wide" onClick={() => addToCart(product)}>Ajouter au panier <span>＋</span></button><div className="detail-note">Paiement sécurisé · Retour sous 14 jours · Assistance 7j/7</div></div></div></section>;
}
function CartDrawer({ onClose }) {
    const { cart, updateCartItem } = useShop();
    return <div className="overlay"><aside className="cart-drawer" role="dialog" aria-modal="true"><div className="drawer-head"><div><p className="eyebrow">VOTRE SÉLECTION</p><h2>Le panier</h2></div><button className="close-button" onClick={onClose}>×</button></div>{!cart?.items?.length ? <div className="drawer-empty"><div className="empty-icon">♡</div><h3>Votre panier est vide</h3><p>Les belles choses commencent souvent par un premier choix.</p><Link to="/catalogue" className="button button-dark" onClick={onClose}>Explorer la boutique</Link></div> : <><div className="cart-items">{cart.items.map((item) => <div className="cart-item" key={item.id}><div className="cart-thumb tone-1"><ProductPlaceholder index={item.id % 4} /></div><div className="cart-item-copy"><strong>{item.product?.name || item.variant?.product?.name}</strong><small>{money(item.unit_price || item.price)}</small><div className="quantity"><button onClick={() => updateCartItem(item.id, item.quantity - 1)}>−</button><span>{item.quantity}</span><button onClick={() => updateCartItem(item.id, item.quantity + 1)}>＋</button></div></div><strong>{money((item.unit_price || item.price) * item.quantity)}</strong></div>)}</div><div className="drawer-total"><span>Total estimé</span><strong>{money(cart.subtotal || cart.total)}</strong></div><Link className="button button-dark button-wide" to="/checkout" onClick={onClose}>Passer la commande <span>↗</span></Link><p className="secure-note">Paiement sécurisé · Livraison calculée à l’étape suivante</p></>}</aside></div>;
}
function AuthModal({ onClose }) {
    const { login, register } = useShop(); const [mode, setMode] = useState('login'); const [values, setValues] = useState({ name:'', email:'', password:'', password_confirmation:'' }); const [error, setError] = useState('');
    const update = (key) => (e) => setValues({ ...values, [key]: e.target.value });
    const submit = async (e) => { e.preventDefault(); setError(''); try { await (mode === 'login' ? login(values) : register(values)); } catch (err) { setError(err.message); } };
    return <div className="overlay"><div className="modal auth-modal" role="dialog" aria-modal="true"><button className="close-button" onClick={onClose}>×</button><div className="auth-intro"><span className="brand-mark">A</span><p className="eyebrow">ANDRÉ SHOP</p><h2>{mode === 'login' ? 'Ravi de vous revoir.' : 'Bienvenue chez nous.'}</h2><p>Retrouvez vos commandes et votre sélection.</p></div><div className="auth-tabs"><button className={mode === 'login' ? 'active' : ''} onClick={() => setMode('login')}>Connexion</button><button className={mode === 'register' ? 'active' : ''} onClick={() => setMode('register')}>Créer un compte</button></div><form onSubmit={submit}>{mode === 'register' && <label>Nom complet<input required value={values.name} onChange={update('name')} /></label>}<label>Adresse email<input required type="email" value={values.email} onChange={update('email')} /></label><label>Mot de passe<input required minLength="6" type="password" value={values.password} onChange={update('password')} /></label>{mode === 'register' && <label>Confirmer le mot de passe<input required type="password" value={values.password_confirmation} onChange={update('password_confirmation')} /></label>}{error && <p className="form-error">{error}</p>}<button className="button button-dark button-wide">{mode === 'login' ? 'Se connecter' : 'Créer mon compte'} <span>↗</span></button></form></div></div>;
}
function CheckoutPage() {
    const { cart, token, setAuthOpen, refreshCart, notify } = useShop(); const navigate = useNavigate(); const [values, setValues] = useState({ name:'', phone:'', address:'', city:'Douala', notes:'' }); const [error, setError] = useState(''); const [submitting, setSubmitting] = useState(false);
    if (!token) return <div className="page-state"><p className="eyebrow">COMMANDE</p><h1>Connectez-vous pour continuer</h1><p>Votre panier et vos informations seront conservés en toute sécurité.</p><button className="button button-dark" onClick={() => setAuthOpen(true)}>Se connecter</button></div>;
    if (!cart?.items?.length) return <div className="page-state"><p className="eyebrow">COMMANDE</p><h1>Votre panier est vide</h1><Link className="button button-dark" to="/catalogue">Découvrir la boutique</Link></div>;
    const update = (key) => (e) => setValues({ ...values, [key]: e.target.value });
    const placeOrder = async (e) => { e.preventDefault(); setSubmitting(true); setError(''); try { const result = await request('/customer/orders', { method:'POST', token, body:{ shipping_address:{ full_name:values.name, phone:values.phone, line1:values.address, city:values.city, country_code:'CM' } } }); const order = result.data || result; const number = order.number || order.order_number; const phone = '+237' + values.phone.replace(/\\D/g,'').replace(/^237/,''); const paymentResult = await request('/customer/orders/' + number + '/payments', { method:'POST', token, body:{ payment_method:'MTN_MOMO', user_phone:phone, idempotency_key:'web-' + Date.now() } }); await refreshCart(); notify('Commande créée. Confirmez le paiement sur votre téléphone.'); navigate('/compte?order=' + number); } catch (err) { setError(err.message); } finally { setSubmitting(false); } };
    return <section className="checkout-page"><div className="checkout-main"><p className="eyebrow">FINALISER LA COMMANDE</p><h1>Quelques derniers détails.</h1><div className="checkout-steps"><span className="active">01 <b>Livraison</b></span><i></i><span>02 <b>Paiement</b></span></div><form onSubmit={placeOrder} className="checkout-form"><div className="form-card"><h2>Où souhaitez-vous être livré ?</h2><div className="form-grid"><label>Nom complet<input required value={values.name} onChange={update('name')} /></label><label>Téléphone<input required value={values.phone} onChange={update('phone')} placeholder="6XX XXX XXX" /></label><label className="full">Adresse de livraison<input required value={values.address} onChange={update('address')} placeholder="Quartier, rue, repère" /></label><label>Ville<select value={values.city} onChange={update('city')}><option>Douala</option><option>Yaoundé</option><option>Bafoussam</option><option>Autre ville</option></select></label><label>Note (optionnel)<input value={values.notes} onChange={update('notes')} /></label></div></div><div className="form-card"><h2>Mode de paiement</h2><div className="payment-choice selected"><span className="radio"></span><div><strong>Mobile Money</strong><small>Orange Money ou MTN MoMo · sécurisé par Nokash</small></div><span className="payment-logos">OM · MTN</span></div></div>{error && <p className="form-error">{error}</p>}<button disabled={submitting} className="button button-dark button-wide">{submitting ? 'Création en cours…' : 'Confirmer la commande'} <span>↗</span></button></form></div><aside className="checkout-summary"><p className="eyebrow">RÉCAPITULATIF</p><h2>Votre sélection</h2>{cart.items.map((item) => <div className="summary-line" key={item.id}><span>{item.quantity} × {item.product?.name || item.variant?.product?.name}</span><strong>{money((item.unit_price || item.price) * item.quantity)}</strong></div>)}<div className="summary-total"><span>Total</span><strong>{money(cart.subtotal || cart.total)}</strong></div><p className="secure-note">✓ Vos données sont protégées<br />✓ Paiement chiffré de bout en bout</p></aside></section>;
}
function AccountPage() {
    const { token, user, setAuthOpen } = useShop();
    const location = useLocation();
    const highlightedOrder = new URLSearchParams(location.search).get('order');
    const [orders, setOrders] = useState([]);
    const [loadingOrders, setLoadingOrders] = useState(false);
    const [ordersError, setOrdersError] = useState('');
    const [expandedOrder, setExpandedOrder] = useState(highlightedOrder || null);
    const statusLabels = { pending_payment: 'Paiement en attente', paid: 'Payée', processing: 'En préparation', shipped: 'Expédiée', delivered: 'Livrée', cancelled: 'Annulée' };
    const loadOrders = async () => {
        if (!token) return;
        setLoadingOrders(true);
        setOrdersError('');
        try {
            const result = await request('/customer/orders', { token });
            setOrders(unwrap(result));
        } catch (error) {
            setOrdersError(error.message);
        } finally {
            setLoadingOrders(false);
        }
    };
    useEffect(() => { loadOrders(); }, [token]);
    if (!token) return <div className="page-state"><h1>Votre espace personnel</h1><p>Connectez-vous pour consulter vos commandes et suivre vos livraisons.</p><button className="button button-dark" onClick={() => setAuthOpen(true)}>Se connecter</button></div>;
    return <section className="account-page"><div className="page-heading"><p className="eyebrow">MON ESPACE</p><h1>Bonjour{user?.name ? `, ${user.name.split(' ')[0]}` : ''}.</h1><p>Retrouvez ici l’historique de vos commandes et suivez leur progression.</p></div><div className="customer-orders-head"><div><p className="eyebrow">HISTORIQUE</p><h2>Mes commandes</h2></div><button className="customer-orders-refresh" onClick={loadOrders} disabled={loadingOrders}>⟳ Actualiser</button></div>{ordersError && <div className="customer-orders-error"><p>{ordersError}</p><button onClick={loadOrders}>Réessayer</button></div>}{loadingOrders ? <div className="customer-orders-loading"><span></span><span></span><span></span></div> : !orders.length ? <div className="customer-orders-empty"><span>▣</span><h3>Vous n’avez pas encore de commande.</h3><p>Vos prochains achats apparaîtront ici.</p><Link className="button button-dark" to="/catalogue">Découvrir les produits <span>↗</span></Link></div> : <div className="customer-orders-list">{orders.map((order) => { const expanded = expandedOrder === order.number; return <article key={order.id} className={'customer-order ' + (highlightedOrder === order.number ? 'customer-order-highlighted' : '')}><button className="customer-order-summary" onClick={() => setExpandedOrder(expanded ? null : order.number)} aria-expanded={expanded}><span className="customer-order-number"><small>COMMANDE</small><strong>{order.number}</strong></span><span><small>DATE</small><strong>{order.placed_at ? new Date(order.placed_at).toLocaleDateString('fr-FR') : '—'}</strong></span><span><small>TOTAL</small><strong>{money(order.total)}</strong></span><span className={'status status-' + order.status}>{statusLabels[order.status] || formatStatus(order.status)}</span><b>{expanded ? '−' : '+'}</b></button>{expanded && <div className="customer-order-details"><div className="customer-order-items">{(order.items || []).map((item) => <div className="customer-order-item" key={item.id}><div><strong>{item.name}</strong><small>{item.sku || 'Article'} · Quantité : {item.quantity}</small></div><span>{money(item.total)}</span></div>)}</div><div className="customer-order-delivery"><small>LIVRAISON</small><strong>{order.shipping_address?.full_name || user?.name}</strong><span>{[order.shipping_address?.line1, order.shipping_address?.city].filter(Boolean).join(', ') || 'Adresse non renseignée'}</span><span>{order.shipping_address?.phone || ''}</span></div></div>}</article>; })}</div>}</section>;
}
function ContactPage() {
    const [values, setValues] = useState({ name: '', email: '', phone: '', subject: '', message: '' });
    const [sent, setSent] = useState(false);
    const update = (key) => (event) => { setValues((current) => ({ ...current, [key]: event.target.value })); setSent(false); };
    const submit = (event) => {
        event.preventDefault();
        const subject = values.subject || 'Demande depuis le site André Shop';
        const body = [
            'Nom : ' + values.name,
            'Email : ' + values.email,
            'Téléphone : ' + values.phone,
            'Sujet : ' + (values.subject || 'Non précisé'),
            '',
            values.message,
        ].join('\n');
        window.location.href = 'mailto:bonjour@andreshop.cm?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
        setSent(true);
    };
    return <section className="contact-page">
        <div className="contact-hero">
            <div className="contact-hero-copy">
                <p className="eyebrow">NOUS CONTACTER</p>
                <h1>Parlons de votre<br /><em>prochain projet.</em></h1>
                <p>Une question sur un équipement, un besoin professionnel ou le suivi d'une commande ? Notre équipe vous répond avec des conseils concrets et adaptés.</p>
                <div className="contact-hero-proof"><span>✓ Réponse sous 24 h</span><span>✓ Conseil personnalisé</span><span>✓ Partout au Cameroun</span></div>
            </div>
            <div className="contact-hero-art"><span className="contact-art-orbit"></span><span className="contact-art-mark">A</span><small>ANDRÉ SHOP<br />SERVICE CLIENT</small><strong>À votre<br /><em>écoute.</em></strong></div>
        </div>
        <div className="contact-layout">
            <aside className="contact-aside">
                <p className="eyebrow">PARLONS-NOUS</p>
                <h2>Une équipe<br /><em>à votre écoute.</em></h2>
                <p>Choisissez le moyen qui vous convient. Pour une demande urgente, WhatsApp reste le plus rapide.</p>
                <div className="contact-details">
                    <a href="tel:+237690713960" className="contact-detail"><span>✆</span><span><small>Téléphone & WhatsApp</small><strong>+237 690 713 960</strong></span><b>↗</b></a>
                    <a href="mailto:bonjour@andreshop.cm" className="contact-detail"><span>✉</span><span><small>Email</small><strong>bonjour@andreshop.cm</strong></span><b>↗</b></a>
                    <div className="contact-detail"><span>⌖</span><span><small>Notre zone de service</small><strong>Douala · Yaoundé · Cameroun</strong></span></div>
                    <div className="contact-detail"><span>◷</span><span><small>Horaires</small><strong>Lundi – Samedi · 8h – 18h</strong></span></div>
                </div>
                <a className="contact-whatsapp" href="https://wa.me/237690713960" target="_blank" rel="noreferrer">Écrire sur WhatsApp <span>↗</span></a>
            </aside>
            <div className="contact-form-card">
                <div className="contact-form-head"><div><p className="eyebrow">VOTRE DEMANDE</p><h2>Comment pouvons-nous vous aider ?</h2></div><span className="contact-form-number">01</span></div>
                {sent && <div className="contact-success" role="status"><span>✓</span><div><strong>Votre demande est prête à être envoyée.</strong><small>Votre messagerie va s'ouvrir pour finaliser l'envoi.</small></div></div>}
                <form className="contact-form" onSubmit={submit}>
                    <div className="contact-form-grid">
                        <label>Nom complet<input required value={values.name} onChange={update('name')} placeholder="Votre nom et prénom" /></label>
                        <label>Adresse email<input required type="email" value={values.email} onChange={update('email')} placeholder="vous@exemple.com" /></label>
                        <label>Téléphone<input required value={values.phone} onChange={update('phone')} placeholder="+237 6XX XXX XXX" /></label>
                        <label>Sujet<select required value={values.subject} onChange={update('subject')}><option value="">Sélectionner un sujet</option><option>Conseil avant achat</option><option>Demande de devis professionnel</option><option>Suivi de commande</option><option>Service après-vente</option><option>Autre demande</option></select></label>
                        <label className="contact-field-full">Votre message<textarea required rows="6" value={values.message} onChange={update('message')} placeholder="Décrivez votre besoin, le produit concerné ou votre projet..." /></label>
                    </div>
                    <label className="contact-consent"><input required type="checkbox" /> <span>J'accepte que André Shop utilise ces informations uniquement pour répondre à ma demande.</span></label>
                    <div className="contact-form-actions"><small>Nous protégeons vos données et ne les partageons jamais.</small><button className="button button-orange" type="submit">Envoyer ma demande <span>↗</span></button></div>
                </form>
            </div>
        </div>
        <div className="contact-service-grid">
            <article><span>01</span><div><strong>Avant votre achat</strong><p>Nous vous aidons à choisir la référence adaptée à votre budget et à votre usage.</p></div><b>↗</b></article>
            <article><span>02</span><div><strong>Pour les professionnels</strong><p>Recevez un devis clair pour vos besoins en volume, installation ou équipement complet.</p></div><b>↗</b></article>
            <article><span>03</span><div><strong>Après votre commande</strong><p>Notre équipe vous accompagne pour la livraison, la mise en service et le service après-vente.</p></div><b>↗</b></article>
        </div>
    </section>;
}
function Footer() { return <footer id="service"><div className="footer-newsletter"><div><p className="eyebrow">RESTONS EN CONTACT</p><h3>Les bonnes idées arrivent<br /><em>au bon moment.</em></h3></div><form onSubmit={(e) => e.preventDefault()}><input type="email" placeholder="Votre adresse email" aria-label="Votre adresse email" /><button className="button button-orange">S'inscrire <span>↗</span></button></form></div><div className="footer-top"><div><Link to="/" className="brand footer-brand"><span className="brand-mark">A</span><span>andré<span className="brand-shop">shop</span></span></Link><p>Votre partenaire équipement.<br />Des solutions qui durent,<br />partout au Cameroun.</p></div><div className="footer-links"><div><strong>La boutique</strong><Link to="/catalogue">Tous les produits</Link><a href="/#catalogue">Nouveautés</a><a href="/#offers">Offres professionnelles</a><a href="/#catalogue">Nos catégories</a></div><div><strong>Services</strong><a href="#service">Livraison & retours</a><a href="/#engagement">Service client</a><a href="#service">Besoin d'aide ?</a><Link to="/contact">Nous contacter</Link></div><div><strong>André Shop</strong><a href="/#engagement">Notre engagement</a><a href="#service">À propos</a><a href="#service">Devenir partenaire</a><a href="#service">Confidentialité</a></div></div></div><div className="footer-bottom"><span>© {new Date().getFullYear()} André Shop. Tous droits réservés.</span><span>Conçu avec soin au Cameroun · Paiement XAF</span></div></footer>; }
function App() { return <BrowserRouter><ShopProvider><AppShell /></ShopProvider></BrowserRouter>; }
createRoot(document.getElementById('root')).render(<App />);


function AdminPage() {
    const { token, user, setAuthOpen, logout, notify } = useShop();
    const [tab, setTab] = useState('overview');
    const [page, setPage] = useState(1);
    const [data, setData] = useState([]);
    const [pagination, setPagination] = useState(null);
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [formTab, setFormTab] = useState(null);
    const sections = [
        ['overview', 'Vue d ensemble', '¦'],
        ['products', 'Produits', '□'],
        ['categories', 'Categories', '◇'],
        ['orders', 'Commandes', '↗'],
        ['inventory', 'Stock', '◫'],
        ['payments', 'Paiements', '◉'],
        ['suppliers', 'Fournisseurs', '♧'],
        ['quotes', 'Demandes de devis', '◇'],
        ['deliveries', 'Livraisons', '⌁'],
    ];
    const resources = { products: 'products', categories: 'categories', orders: 'orders', inventory: 'inventory', payments: 'payments', suppliers: 'suppliers', quotes: 'quote-requests', deliveries: 'deliveries' };
    const load = async (section = tab, pageNumber = 1) => {
        setLoading(true); setError('');
        try {
            if (section === 'overview') {
                const overviewResources = ['products', 'orders', 'inventory', 'payments'];
                const [products, orders, inventory, payments] = await Promise.all(overviewResources.map((key) => request('/admin/' + resources[key], { token })));
                setStats({ products: unwrap(products), orders: unwrap(orders), inventory: unwrap(inventory), payments: unwrap(payments) });
                setData([]);
                setPagination(null);
            } else {
                const result = await request('/admin/' + resources[section] + '?page=' + pageNumber, { token });
                setData(unwrap(result));
                setPagination(result.meta || null);
            }
        } catch (e) { setError(e.message); } finally { setLoading(false); }
    };
    useEffect(() => { if (token && user?.role === 'admin') load(tab, tab === 'overview' ? 1 : page); }, [tab, page, token, user]);
    useEffect(() => { if (pagination?.last_page && page > pagination.last_page) setPage(pagination.last_page); }, [pagination, page]);
    if (!token || !user) return <div className="admin-gate"><div className="admin-gate-card"><span className="brand-mark">A</span><p className="eyebrow">ESPACE ADMINISTRATEUR</p><h1>Connectez-vous pour gerer Andre Shop.</h1><p>Accedez au catalogue, aux commandes, au stock et aux operations.</p><button className="button button-dark" onClick={() => setAuthOpen(true)}>Ouvrir la connexion <span>↗</span></button></div></div>;
    if (user.role !== 'admin') return <div className="admin-gate"><div className="admin-gate-card"><span className="brand-mark">!</span><p className="eyebrow">ACCES RESTREINT</p><h1>Cette zone est reservee aux administrateurs.</h1><p>Connectez-vous avec un compte disposant du role administrateur.</p><button className="button button-dark" onClick={logout}>Se deconnecter</button></div></div>;
    const changeSection = (key) => { setTab(key); setPage(1); setPagination(null); };
    const refresh = () => load(tab, tab === 'overview' ? 1 : page);
    return <section className="admin-layout"><aside className="admin-sidebar"><Link to="/" className="admin-brand"><span className="brand-mark">A</span><span>andre<span>shop</span></span></Link><div className="admin-profile"><span className="admin-avatar">{(user.name || 'A').slice(0, 1).toUpperCase()}</span><div><strong>{user.name}</strong><small>Administrateur</small></div></div><nav className="admin-nav">{sections.map(([key, label, icon]) => <button key={key} className={tab === key ? 'active' : ''} onClick={() => changeSection(key)}><span>{icon}</span>{label}</button>)}</nav><button className="admin-logout" onClick={logout}>↪ Se deconnecter</button></aside><div className="admin-content"><header className="admin-topbar"><div><p className="eyebrow">ANDRE SHOP / ADMIN</p><h1>{sections.find(([key]) => key === tab)?.[1]}</h1></div><div className="admin-top-actions"><Link to="/" className="admin-view-store">Voir la boutique ↗</Link><button className="icon-button" onClick={refresh} aria-label="Actualiser">⟳</button></div></header>{error && <div className="admin-error">{error}</div>}{loading ? <div className="admin-loading"><span></span><span></span><span></span></div> : tab === 'overview' ? <AdminOverview stats={stats} money={money} /> : <AdminTable tab={tab} rows={data} pagination={pagination} onPageChange={setPage} token={token} refresh={refresh} money={money} notify={notify} onCreate={() => setFormTab(tab)} />}</div>{formTab && <AdminFormModal tab={formTab} token={token} onClose={() => setFormTab(null)} onSaved={() => { setFormTab(null); refresh(); }} notify={notify} />}</section>;
}
function AdminOverview({ stats, money }) {
    const products = stats?.products || [], orders = stats?.orders || [], inventory = stats?.inventory || [], payments = stats?.payments || [];
    const lowStock = inventory.filter((item) => item.is_low_stock);
    const paid = payments.filter((item) => item.status === 'succeeded').reduce((total, item) => total + Number(item.amount || 0), 0);
    return <div className="admin-overview"><div className="admin-welcome"><div><p className="eyebrow">RÉSUMÉ OPÉRATIONNEL</p><h2>Une vue claire pour décider vite.</h2><p>Suivez votre activité commerciale et les opérations prioritaires depuis un seul espace.</p></div><div className="admin-welcome-shape">A</div></div><div className="admin-kpis"><div><span>Produits actifs</span><strong>{products.length}</strong><small>Références au catalogue</small></div><div><span>Commandes</span><strong>{orders.length}</strong><small>Toutes périodes</small></div><div><span>Paiements confirmés</span><strong>{money(paid)}</strong><small>Transactions réussies</small></div><div className={lowStock.length ? 'warning' : ''}><span>Alertes stock</span><strong>{lowStock.length}</strong><small>{lowStock.length ? 'À réapprovisionner' : 'Tout est sous contrôle'}</small></div></div><div className="admin-panels"><div className="admin-panel"><div className="panel-heading"><div><p className="eyebrow">ACTIVITÉ RÉCENTE</p><h3>Dernières commandes</h3></div><span>↗</span></div>{orders.slice(0, 5).map((order) => <div className="admin-list-row" key={order.id}><div><strong>{order.number}</strong><small>{order.user?.name || 'Client'}</small></div><span className={`status status-${order.status}`}>{formatStatus(order.status)}</span><b>{money(order.total)}</b></div>)}{!orders.length && <p className="admin-muted">Aucune commande pour le moment.</p>}</div><div className="admin-panel"><div className="panel-heading"><div><p className="eyebrow">VIGILANCE</p><h3>Stock à surveiller</h3></div><span>◫</span></div>{lowStock.slice(0, 5).map((item) => <div className="admin-list-row" key={item.id}><div><strong>{item.name || item.sku}</strong><small>{item.sku || 'Produit'}</small></div><span className="status status-warning">{item.available} disponible{item.available > 1 ? 's' : ''}</span></div>)}{!lowStock.length && <p className="admin-muted">Aucune alerte de stock.</p>}</div></div></div>;
}
function formatStatus(value) { return String(value || '').replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()); }
function AdminTable({ tab, rows, pagination, onPageChange, token, refresh, money, notify, onCreate }) {
    const titles = {
        products: ['Produit', 'Categorie', 'Prix', 'Etat'],
        categories: ['Categorie', 'Slug', 'Description', 'Etat'],
        orders: ['Commande', 'Client', 'Total', 'Statut'],
        inventory: ['Article', 'SKU', 'Disponible', 'Niveau'],
        payments: ['Reference', 'Commande', 'Montant', 'Statut'],
        suppliers: ['Fournisseur', 'Contact', 'Telephone', 'Etat'],
        quotes: ['Reference', 'Fournisseur', 'Total', 'Statut'],
        deliveries: ['Suivi', 'Commande', 'Destinataire', 'Statut']
    };
    const [stockAction, setStockAction] = useState(null);
    const [activeAction, setActiveAction] = useState(null);
    const updateStatus = async (id, status) => {
        try {
            const path = tab === 'quotes'
                ? '/admin/quote-requests/' + id + '/status'
                : tab === 'orders'
                    ? '/admin/orders/' + id + '/status'
                    : '/admin/deliveries/' + id + '/status';
            await request(path, { method: 'PATCH', token, body: { status } });
            notify('Statut mis a jour.');
            refresh();
        } catch (e) { notify(e.message, 'error'); }
    };
    const remove = async (row) => {
        if (!window.confirm('Supprimer cet element ? Cette action est irreversible.')) return;
        try {
            await request('/admin/' + tab + '/' + row.id, { method: 'DELETE', token });
            notify('Element supprime.');
            refresh();
        } catch (e) { notify(e.message, 'error'); }
    };
    const toggleActive = async (row) => {
        const isActive = row.is_active !== false;
        const resourceName = tab === 'products' ? 'Produit' : 'Categorie';
        setActiveAction(row.id);
        try {
            await request('/admin/' + tab + '/' + row.slug, {
                method: 'PATCH',
                token,
                body: { is_active: !isActive }
            });
            notify(resourceName + (isActive ? ' desactive.' : ' active.'));
            refresh();
        } catch (e) {
            notify(e.message, 'error');
        } finally {
            setActiveAction(null);
        }
    };
    const statusOptions = {
        orders: ['pending_payment','paid','processing','shipped','delivered','cancelled'],
        quotes: ['draft','sent','received','accepted','rejected','cancelled'],
        deliveries: ['pending','assigned','picked_up','in_transit','delivered','failed','cancelled']
    };
    const canCreate = ['categories', 'products', 'suppliers'].includes(tab);
    const hasActions = canCreate || tab === 'inventory';
    return <>
        <div className="admin-table-card">
            <div className="admin-table-head">
                <div><p className="eyebrow">GESTION</p><h2>{titles[tab][0]}{rows.length > 1 ? 's' : ''}</h2></div>
                <div className="admin-table-actions">{canCreate && <button className="button button-dark admin-primary-action" onClick={onCreate}>+ Ajouter</button>}<span className="admin-count">{rows.length} element{rows.length > 1 ? 's' : ''}</span></div>
            </div>
            <div className="table-scroll">
                <table className="admin-table">
                    <thead><tr>{titles[tab].map((title) => <th key={title}>{title}</th>)}{hasActions && <th>Actions</th>}</tr></thead>
                    <tbody>{rows.map((row) => <tr key={row.id}>
                        {tab === 'products' && <><td><div className="admin-entity-cell"><span className="admin-entity-thumbnail">{(row.images?.find((image) => image.is_primary)?.url || row.images?.[0]?.url || row.image_url) ? <img src={row.images?.find((image) => image.is_primary)?.url || row.images?.[0]?.url || row.image_url} alt={row.name} loading="lazy" /> : <span aria-hidden="true">A</span>}</span><span className="admin-entity-copy"><strong>{row.name}</strong><small>{row.sku}</small></span></div></td><td>{row.category?.name || '—'}</td><td>{money(row.price)}</td><td><span className={'status ' + (row.is_active === false ? 'status-cancelled' : 'status-success')}>{row.is_active === false ? 'Inactif' : 'Actif'}</span></td></>}
                        {tab === 'categories' && <><td><div className="admin-entity-cell"><span className="admin-entity-thumbnail">{row.image_url ? <img src={row.image_url} alt={row.name} loading="lazy" /> : <span aria-hidden="true">A</span>}</span><span className="admin-entity-copy"><strong>{row.name}</strong></span></div></td><td><small>{row.slug || '—'}</small></td><td>{row.description || '—'}</td><td><span className={'status ' + (row.is_active === false ? 'status-cancelled' : 'status-success')}>{row.is_active === false ? 'Inactive' : 'Active'}</span></td></>}
                        {tab === 'orders' && <><td><strong>{row.number}</strong><small>{row.placed_at ? new Date(row.placed_at).toLocaleDateString('fr-FR') : '—'}</small></td><td>{row.user?.name || 'Client'}</td><td>{money(row.total)}</td><td><select className="status-select" value={row.status} onChange={(e) => updateStatus(row.id, e.target.value)}>{statusOptions.orders.map((status) => <option key={status} value={status}>{formatStatus(status)}</option>)}</select></td></>}
                        {tab === 'inventory' && <><td><strong>{row.name || '—'}</strong><small>{row.stockable_type === 'variant' ? 'Variante' : 'Produit'}</small></td><td>{row.sku || '—'}</td><td><strong>{row.available}</strong> / {row.on_hand}<small>{row.reserved} reserve{row.reserved > 1 ? 's' : ''}</small></td><td>{row.reorder_level}{row.is_low_stock && <span className="status status-warning admin-low-stock">Faible</span>}</td></>}
                        {tab === 'payments' && <><td><strong>{row.reference}</strong><small>{row.provider || 'nokash'}</small></td><td>{row.order?.number || '—'}</td><td>{money(row.amount)}</td><td><span className={'status status-' + row.status}>{formatStatus(row.status)}</span></td></>}
                        {tab === 'suppliers' && <><td><strong>{row.name}</strong><small>{row.email}</small></td><td>{row.contact_name || '—'}</td><td>{row.phone || '—'}</td><td><span className={'status ' + (row.is_active === false ? 'status-cancelled' : 'status-success')}>{row.is_active === false ? 'Inactif' : 'Actif'}</span></td></>}
                        {tab === 'quotes' && <><td><strong>{row.reference}</strong><small>{row.requested_at ? new Date(row.requested_at).toLocaleDateString('fr-FR') : '—'}</small></td><td>{row.supplier?.name || '—'}</td><td>{money(row.total)}</td><td><select className="status-select" value={row.status} onChange={(e) => updateStatus(row.id, e.target.value)}>{statusOptions.quotes.map((status) => <option key={status} value={status}>{formatStatus(status)}</option>)}</select></td></>}
                        {tab === 'deliveries' && <><td><strong>{row.tracking_number || 'Non attribue'}</strong><small>{row.provider || 'Transport interne'}</small></td><td>{row.order?.number || '—'}</td><td>{row.recipient_name || '—'}</td><td><select className="status-select" value={row.status} onChange={(e) => updateStatus(row.id, e.target.value)}>{statusOptions.deliveries.map((status) => <option key={status} value={status}>{formatStatus(status)}</option>)}</select></td></>}
                        {tab === 'inventory' && <td><div className="admin-stock-actions"><button className="admin-stock-action admin-stock-receive" onClick={() => setStockAction({ item: row, mode: 'receive' })}>Réception</button><button className="admin-stock-action admin-stock-adjust" onClick={() => setStockAction({ item: row, mode: 'adjust' })}>Ajuster</button></div></td>}
                        {canCreate && <td><div className="admin-row-actions">{['categories', 'products'].includes(tab) && <button disabled={activeAction === row.id} className={'admin-toggle ' + (row.is_active === false ? 'admin-toggle-activate' : 'admin-toggle-deactivate')} onClick={() => toggleActive(row)}>{activeAction === row.id ? 'Patientez...' : row.is_active === false ? 'Activer' : 'Desactiver'}</button>}<button className="admin-delete" onClick={() => remove(row)}>Supprimer</button></div></td>}
                    </tr>)}</tbody>
                </table>
            </div>
            {!rows.length && <div className="admin-empty"><span>◌</span><strong>Aucune donnee disponible</strong><p>Les nouveaux elements apparaitront ici.</p></div>}
        </div>
        <AdminPagination pagination={pagination} onPageChange={onPageChange} />
        {stockAction && <StockActionModal item={stockAction.item} mode={stockAction.mode} token={token} notify={notify} onClose={() => setStockAction(null)} onSaved={() => { setStockAction(null); refresh(); }} />}
    </>;
}
function AdminPagination({ pagination, onPageChange }) {
    if (!pagination || Number(pagination.last_page || 1) <= 1) return null;
    const current = Number(pagination.current_page || 1);
    const last = Number(pagination.last_page || 1);
    const pages = [];
    for (let number = 1; number <= last; number += 1) {
        if (last <= 7 || number === 1 || number === last || Math.abs(number - current) <= 1) pages.push(number);
        else if (pages[pages.length - 1] !== 'ellipsis') pages.push('ellipsis');
    }
    return <nav className="admin-pagination" aria-label="Pagination">
        <span>Affichage {pagination.from || 0}–{pagination.to || 0} sur {pagination.total || 0}</span>
        <div className="admin-pagination-controls">
            <button type="button" disabled={current <= 1} onClick={() => onPageChange(current - 1)} aria-label="Page précédente">←</button>
            {pages.map((page, index) => page === 'ellipsis'
                ? <span className="admin-pagination-ellipsis" key={'ellipsis-' + index}>…</span>
                : <button type="button" key={page} className={page === current ? 'active' : ''} onClick={() => onPageChange(page)}>{page}</button>)}
            <button type="button" disabled={current >= last} onClick={() => onPageChange(current + 1)} aria-label="Page suivante">→</button>
        </div>
    </nav>;
}
function StockActionModal({ item, mode, token, notify, onClose, onSaved }) {
    const receiving = mode === 'receive';
    const [quantity, setQuantity] = useState('');
    const [reason, setReason] = useState(receiving ? 'Réception fournisseur' : 'Correction inventaire');
    const [reference, setReference] = useState('');
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        setError('');
        try {
            const numericQuantity = Number(quantity);
            if (!Number.isInteger(numericQuantity) || (receiving ? numericQuantity < 1 : numericQuantity === 0)) {
                throw new Error(receiving ? 'La quantité doit être un entier positif.' : 'La quantité doit être un entier différent de zéro.');
            }
            await request('/admin/inventory/' + item.id + '/' + mode, {
                method: 'POST',
                token,
                body: { quantity: numericQuantity, reason, reference: reference || null }
            });
            notify(receiving ? 'Réception de stock enregistrée.' : 'Ajustement de stock enregistré.');
            onSaved();
        } catch (e) {
            setError(e.message);
        } finally { setSaving(false); }
    };
    return <div className="overlay admin-modal-overlay" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}>
        <div className="admin-form-modal stock-action-modal" role="dialog" aria-modal="true" aria-labelledby="stock-action-title">
            <div className="drawer-head"><div><p className="eyebrow">{receiving ? 'ENTREE DE STOCK' : 'AJUSTEMENT DE STOCK'}</p><h2 id="stock-action-title">{receiving ? 'Recevoir une quantité' : 'Corriger le stock'}</h2></div><button className="close-button" onClick={onClose} aria-label="Fermer">×</button></div>
            <div className="stock-action-summary"><strong>{item.name || item.sku}</strong><span>Stock actuel : {item.on_hand} · Disponible : {item.available}</span></div>
            <form className="admin-form stock-action-form" onSubmit={submit}>
                <label className="full">Quantité {receiving ? 'reçue' : 'à ajuster'}<input required type="number" step="1" min={receiving ? '1' : undefined} value={quantity} onChange={(event) => setQuantity(event.target.value)} placeholder={receiving ? 'Ex. 20' : 'Ex. 5 ou -2'} /></label>
                <label className="full">Motif<input required value={reason} onChange={(event) => setReason(event.target.value)} placeholder="Motif de l'opération" /></label>
                <label className="full">Référence (optionnel)<input value={reference} onChange={(event) => setReference(event.target.value)} placeholder="Bon de livraison, inventaire..." /></label>
                {error && <p className="form-error">{error}</p>}
                <div className="admin-form-actions"><button type="button" className="button button-light" onClick={onClose}>Annuler</button><button disabled={saving} className="button button-dark" type="submit">{saving ? 'Enregistrement...' : receiving ? 'Enregistrer la réception' : 'Enregistrer l’ajustement'} <span>↗</span></button></div>
            </form>
        </div>
    </div>;
}
function AdminFormModal({ tab, token, onClose, onSaved, notify }) {
    const initial = {
        categories: { name: '', description: '', is_active: true },
        products: { category_id: '', name: '', sku: '', price: '', initial_stock: '0', reorder_level: '3', description: '', is_active: true },
        suppliers: { name: '', contact_name: '', email: '', phone: '', address: '', notes: '', is_active: true }
    };
    const [values, setValues] = useState(initial[tab] || {});
    const [categories, setCategories] = useState([]);
    const [error, setError] = useState('');
    const [saving, setSaving] = useState(false);
    const [imageFile, setImageFile] = useState(null);
    const [imagePreview, setImagePreview] = useState('');
    useEffect(() => () => { if (imagePreview) URL.revokeObjectURL(imagePreview); }, [imagePreview]);
    useEffect(() => { if (tab === 'products') request('/admin/categories', { token }).then((result) => setCategories(unwrap(result))).catch(() => setCategories([])); }, [tab, token]);
    const update = (key) => (event) => { const value = event.target.type === 'checkbox' ? event.target.checked : event.target.value; setValues((current) => ({ ...current, [key]: value })); };
    const handleImageChange = (event) => { const file = event.target.files?.[0] || null; setImageFile(file); setImagePreview(file ? URL.createObjectURL(file) : ''); };
    const submit = async (event) => {
        event.preventDefault(); setSaving(true); setError('');
        const payload = { ...values };
        if (tab === 'products' && !payload.category_id) payload.category_id = null;
        if (tab === 'products' && !String(payload.sku || '').trim()) {
            const slugBase = String(payload.name || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-zA-Z0-9]+/g, '-').replace(/^-+|-+$/g, '').toUpperCase().slice(0, 90);
            payload.sku = slugBase ? 'AND-' + slugBase : '';
        }
        try {
            const requestPayload = { ...payload };
            if (tab === 'products') {
                delete requestPayload.initial_stock;
                delete requestPayload.reorder_level;
            }
            let requestBody = requestPayload;
            if (tab === 'categories' && imageFile) {
                const categoryPayload = new FormData();
                categoryPayload.append('name', payload.name);
                categoryPayload.append('description', payload.description || '');
                categoryPayload.append('is_active', payload.is_active ? '1' : '0');
                categoryPayload.append('image', imageFile);
                requestBody = categoryPayload;
            }
            const result = await request('/admin/' + tab, { method: 'POST', token, body: requestBody });
            if (tab === 'products') {
                const product = unwrap(result);
                await request('/admin/inventory', {
                    method: 'POST',
                    token,
                    body: {
                        product_id: product.id,
                        on_hand: Number(payload.initial_stock),
                        reorder_level: Number(payload.reorder_level),
                        reason: 'Stock initial produit',
                        reference: payload.sku
                    }
                });
                if (imageFile) {
                    const imagePayload = new FormData();
                    imagePayload.append('image', imageFile);
                    imagePayload.append('alt_text', payload.name);
                    imagePayload.append('is_primary', '1');
                    await request('/admin/products/' + product.slug + '/images', { method: 'POST', token, body: imagePayload });
                }
            }
            notify(tab === 'products' ? (imageFile ? 'Produit, stock et image enregistres.' : 'Produit et stock initial crees.') : tab === 'categories' ? (imageFile ? 'Categorie et image enregistrees.' : 'Categorie creee.') : 'Fournisseur cree.');
            onSaved();
        } catch (e) {
            setError(e.message);
        } finally {
            setSaving(false);
        }
    };    const title = tab === 'products' ? 'Ajouter un produit' : tab === 'categories' ? 'Ajouter une categorie' : 'Ajouter un fournisseur';
    return <div className="overlay admin-modal-overlay" onMouseDown={(event) => { if (event.target === event.currentTarget) onClose(); }}><div className="admin-form-modal" role="dialog" aria-modal="true" aria-labelledby="admin-form-title"><div className="drawer-head"><div><p className="eyebrow">NOUVEL ELEMENT</p><h2 id="admin-form-title">{title}</h2></div><button className="close-button" onClick={onClose} aria-label="Fermer">×</button></div><form className="admin-form" onSubmit={submit}>{tab === 'categories' && <><label>Nom<input required value={values.name} onChange={update('name')} /></label><label>Description<textarea rows="3" value={values.description} onChange={update('description')} /></label><label className="full image-upload-field">Image de la catégorie<input type="file" accept="image/jpeg,image/png,image/webp" onChange={handleImageChange} />{imagePreview ? <span className="image-preview"><img src={imagePreview} alt="Aperçu de la catégorie" /></span> : <span className="image-preview-empty">Aperçu de l’image disponible après sélection</span>}{imageFile && <small className="image-file-name">{imageFile.name}</small>}</label></>}{tab === 'products' && <><label>Nom<input required value={values.name} onChange={update('name')} /></label><label>Categorie<select value={values.category_id} onChange={update('category_id')}><option value="">Sans categorie</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label><label>Prix (FCFA)<input required min="0" step="1" type="number" value={values.price} onChange={update('price')} /></label><label>Quantite initiale<input required min="0" step="1" type="number" value={values.initial_stock} onChange={update('initial_stock')} /></label><label>Seuil d'alerte<input required min="0" step="1" type="number" value={values.reorder_level} onChange={update('reorder_level')} /></label><label className="full image-upload-field">Image du produit<input type="file" accept="image/jpeg,image/png,image/webp" onChange={handleImageChange} />{imagePreview ? <span className="image-preview"><img src={imagePreview} alt="Aperçu du produit" /></span> : <span className="image-preview-empty">Aperçu de l’image disponible après sélection</span>}{imageFile && <small className="image-file-name">{imageFile.name}</small>}</label><label className="full">Description<textarea rows="3" value={values.description} onChange={update('description')} /></label></>}{tab === 'suppliers' && <><label>Nom<input required value={values.name} onChange={update('name')} /></label><label>Contact<input value={values.contact_name} onChange={update('contact_name')} /></label><label>Email<input type="email" value={values.email} onChange={update('email')} /></label><label>Telephone<input value={values.phone} onChange={update('phone')} /></label><label className="full">Adresse<textarea rows="2" value={values.address} onChange={update('address')} /></label><label className="full">Notes<textarea rows="2" value={values.notes} onChange={update('notes')} /></label></>}<label className="admin-checkbox"><input type="checkbox" checked={values.is_active} onChange={update('is_active')} /> Element actif</label>{error && <p className="form-error">{error}</p>}<div className="admin-form-actions"><button type="button" className="button button-light" onClick={onClose}>Annuler</button><button disabled={saving} className="button button-dark">{saving ? 'Enregistrement...' : 'Enregistrer'} <span>↗</span></button></div></form></div></div>;
}
