FROM ubuntu:18.04 as baseimage1804
LABEL maintainer tpfuemp@github

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    automake autotools-dev build-essential dh-autoreconf \
    ca-certificates git \
    libboost-chrono-dev libboost-filesystem-dev libboost-program-options-dev libboost-system-dev libboost-test-dev libboost-thread-dev libevent-dev \
    libssl1.0-dev libgmp-dev libsodium-dev libz-dev bsdmainutils curl libsqlite3-dev \
    libtool pkg-config && \
# 3. install db v4.8
    apt-get install -y --no-install-recommends software-properties-common && \
    add-apt-repository ppa:bitcoin/bitcoin && \
    apt-get update && \
    apt-get install -y --no-install-recommends libdb4.8-dev libdb4.8++-dev && \
    apt-get install -y --no-install-recommends mc psmisc wget protobuf-compiler libminiupnpc-dev g++ curl cmake bison libevent-dev libboost-dev libxml2-dev python3 flex dumb-init && \
    apt-get clean

FROM baseimage1804 as coinwallet

# Clone the source code.
ARG STRATUM_UPDATEPASS
ARG CURRENT_GITHUB_COMMIT=beae01d62292a0aab363b7a4d3f606708cea7260

RUN git clone https://github.com/litecoin-project/litecoin
WORKDIR /litecoin

# use the specific commit known to work
RUN git checkout $CURRENT_GITHUB_COMMIT

# apply patch if needed
#COPY docker.patch /tmp/
#RUN patch -p1 < /tmp/docker.patch

RUN cd depends && make && cd .. && \
    ./autogen.sh && \
    ./configure --prefix `pwd`/depends/x86_64-pc-linux-gnu --with-gui=no --disable-tests --disable-bench --enable-cxx --disable-shared --with-pic CPPFLAGS=-DUSE_UPNP=0 && make clean && make && \
    strip src/litecoind && cp src/litecoind /usr/bin && \
    cd / && rm /litecoin -rf && \
    rm ~/.cache -rf && rm ~/.ccache -rf && rm ~/.cargo -rf

# blocknotify
COPY blocknotify.cpp /tmp/
WORKDIR /tmp
RUN gcc -DSTRATUM_UPDATEPASS=\"$STRATUM_UPDATEPASS\" -o blocknotify blocknotify.cpp && \
    mv blocknotify /usr/bin/ && rm -f /tmp/blocknotify.cpp

ARG walletuserid
ARG walletgroupid
RUN groupadd -g $walletgroupid walletuser && useradd -r -u $walletuserid -g walletuser walletuser
USER walletuser 
