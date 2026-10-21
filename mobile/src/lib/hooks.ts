import { useEffect, useState } from 'react';

/** O valor só muda depois de $delay ms sem mudanças: a busca não chama a API a cada letra. */
export function useDebouncedValue<T>(value: T, delay = 300): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delay);

    return () => clearTimeout(timer);
  }, [value, delay]);

  return debounced;
}
