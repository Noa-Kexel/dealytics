/** Helper fetch authentifié (cookie XSRF Laravel). */

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

interface ApiOptions {
    method?: string;
    body?: Record<string, unknown>;
    params?: Record<string, string>;
}

export async function api<T = unknown>(url: string, options: ApiOptions = {}): Promise<T> {
    const { method = 'GET', body, params } = options;

    let fullUrl = url;

    if (params) {
        const searchParams = new URLSearchParams(params);
        fullUrl += `?${searchParams.toString()}`;
    }

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-XSRF-TOKEN': getCsrfToken(),
    };

    const fetchOptions: RequestInit = {
        method,
        headers,
        credentials: 'same-origin',
    };

    if (body) {
        headers['Content-Type'] = 'application/json';
        fetchOptions.body = JSON.stringify(body);
    }

    fetchOptions.headers = headers;

    const response = await fetch(fullUrl, fetchOptions);

    if (!response.ok) {
        throw new Error(`API error: ${response.status}`);
    }

    return response.json() as Promise<T>;
}
