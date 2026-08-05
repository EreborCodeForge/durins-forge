import { createApp, h, shallowRef } from 'vue';
import '../css/app.css';
import '../css/dashboard.css';

// The MithrilVue Client Bridge
const MithrilVue = {
    install(app) {
        // Global property or composables can be added here
    }
};

async function resolveComponent(name) {
    const pages = import.meta.glob('./Pages/**/*.vue');
    const path = `./Pages/${name}.vue`;
    
    if (!pages[path]) {
        throw new Error(`Page not found: ${name}`);
    }
    
    const module = await pages[path]();
    return module.default;
}

async function createInertiaApp() {
    const el = document.getElementById('app');
    if (!el) return; // Not a Mithril-Vue page

    const initialPage = JSON.parse(el.dataset.page);
    
    // Reactive state for the current page
    const page = shallowRef(initialPage);
    const component = shallowRef(null);

    // Load initial component
    component.value = await resolveComponent(initialPage.component);

    const App = {
        setup() {
            return () => {
                if (!component.value) return h('div', 'Loading...');
                
                return h(component.value, page.value.props);
            };
        }
    };

    createApp(App)
        .use(MithrilVue)
        .mount(el);

    document.addEventListener('click', async (e) => {
        const link = e.target.closest('a[mithril-link]');
        if (link) {
            e.preventDefault();
            const url = link.href;
            
            history.pushState({}, '', url);

            try {
                const response = await fetch(url, {
                    headers: {
                        'X-Mithril-Vue': 'true',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': page.value.props.csrf_token
                    }
                });
                
                const data = await response.json();
                
                page.value = data;
                component.value = await resolveComponent(data.component);
                
            } catch (error) {
                console.error('Navigation error:', error);
                window.location.href = url;
            }
        }
    });

    // Handle Back/Forward buttons
    window.addEventListener('popstate', () => {
        window.location.reload(); // Simple fallback for now
    });
}

createInertiaApp();
