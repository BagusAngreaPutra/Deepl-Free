import unittest
from unittest.mock import Mock, patch
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))
import translator_core


class GoogleFallbackTest(unittest.TestCase):
    @patch('requests.get')
    def test_uses_clients_endpoint_when_googleapis_dns_fails(self, get):
        dns_failure = ConnectionError('Failed to resolve translate.googleapis.com')
        clients_response = Mock()
        clients_response.raise_for_status.return_value = None
        clients_response.json.return_value = ['Good morning']
        get.side_effect = [dns_failure, clients_response]

        translated = translator_core._translate_with_google_fallbacks(
            'selamat pagi', 'id', 'en-GB'
        )

        self.assertEqual('Good morning', translated)
        self.assertEqual(2, get.call_count)
        self.assertIn('clients5.google.com', get.call_args.args[0])

    @patch('requests.get')
    def test_parses_googleapis_segment_response(self, get):
        response = Mock()
        response.raise_for_status.return_value = None
        response.json.return_value = [[['Good ', 'Selamat '], ['morning', 'pagi']]]
        get.return_value = response

        translated = translator_core._translate_with_google_fallbacks(
            'selamat pagi', 'id', 'en-GB'
        )

        self.assertEqual('Good morning', translated)
        self.assertEqual(1, get.call_count)


class ResilientDnsTest(unittest.TestCase):
    def tearDown(self):
        translator_core.socket.getaddrinfo = translator_core._system_getaddrinfo

    @patch.object(translator_core, '_save_dns_cache')
    @patch.object(translator_core, '_dns_cache_path')
    @patch.object(translator_core, '_system_getaddrinfo')
    def test_uses_cached_address_when_system_dns_fails(self, getaddrinfo, cache_path, save):
        cache_path.return_value = 'missing-dns-cache.json'
        cached = [(2, 1, 6, '', ('142.250.4.95', 443))]
        translator_core._dns_cache = {'translate.googleapis.com': cached}
        getaddrinfo.side_effect = translator_core.socket.gaierror('DNS unavailable')

        translator_core.install_resilient_dns(('translate.googleapis.com',))
        result = translator_core.socket.getaddrinfo(
            'translate.googleapis.com', 443, type=translator_core.socket.SOCK_STREAM
        )

        self.assertEqual('142.250.4.95', result[0][4][0])
        save.assert_not_called()


if __name__ == '__main__':
    unittest.main()
