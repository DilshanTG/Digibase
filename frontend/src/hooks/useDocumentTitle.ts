import { useEffect } from 'react';

/**
 * Custom hook to set document title dynamically for SEO
 * @param title - The page title to set
 * @param suffix - Optional suffix, defaults to "Digibase"
 */
export function useDocumentTitle(title: string, suffix: string = 'Digibase') {
    useEffect(() => {
        const fullTitle = title ? `${title} | ${suffix}` : suffix;
        document.title = fullTitle;

        // Update meta tags for SPA navigation
        const ogTitle = document.querySelector('meta[property="og:title"]');

        if (ogTitle) {
            ogTitle.setAttribute('content', fullTitle);
        }

        return () => {
            // Reset to default on unmount (optional)
            document.title = 'Digibase - Open Source Laravel BaaS';
        };
    }, [title, suffix]);
}
