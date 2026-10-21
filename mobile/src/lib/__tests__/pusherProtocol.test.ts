import { parseFrame, reconnectDelay, socketUrl, subscribeFrame } from '../pusherProtocol';

describe('parseFrame', () => {
  it('reads the data that comes as JSON text inside the JSON', () => {
    const raw = JSON.stringify({
      event: 'assistant.delta',
      channel: 'private-user.1',
      data: JSON.stringify({ message_id: 7, seq: 2, text: 'Oi' }),
    });

    expect(parseFrame(raw)).toEqual({
      event: 'assistant.delta',
      channel: 'private-user.1',
      data: { message_id: 7, seq: 2, text: 'Oi' },
    });
  });

  it('keeps data that is already an object or plain text', () => {
    expect(parseFrame('{"event":"pusher:ping","data":{}}')).toEqual({ event: 'pusher:ping', channel: undefined, data: {} });
    expect(parseFrame('{"event":"x","data":"texto"}')?.data).toBe('texto');
  });

  it('ignores broken frames instead of breaking the connection', () => {
    expect(parseFrame('não é json')).toBeNull();
    expect(parseFrame('{"sem":"evento"}')).toBeNull();
  });
});

describe('the frames the app sends', () => {
  it('builds the socket address and the subscription', () => {
    expect(socketUrl('ws://10.0.2.2:8180/', 'chave')).toBe('ws://10.0.2.2:8180/app/chave?protocol=7&client=js&version=8.4.0&flash=false');
    expect(JSON.parse(subscribeFrame('private-user.1', 'chave:assinatura'))).toEqual({
      event: 'pusher:subscribe',
      data: { channel: 'private-user.1', auth: 'chave:assinatura' },
    });
  });
});

describe('reconnectDelay', () => {
  it('waits a little more each time, up to 15 seconds', () => {
    expect([0, 1, 2, 3, 4, 10].map(reconnectDelay)).toEqual([1000, 2000, 5000, 10_000, 15_000, 15_000]);
  });
});
